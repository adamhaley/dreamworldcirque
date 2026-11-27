<?php

namespace App\Models;

use App\Enums\ProspectClassification;
use App\Enums\ProspectStatus;
use App\Events\RecordPipelineStatusChanged;
use App\Jobs\ClassifyProspect;
use App\Jobs\GenerateProspectReply;
use App\Mail\ProspectReplyMail;
use Database\Factories\ProspectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;

/**
 * @property list<string>|null $interests
 * @property array<string, mixed>|null $raw_payload
 * @property list<array{disk: string, path: string, name: string}>|null $reply_attachments
 */
#[Fillable([
    'source', 'source_external_id', 'submitted_at', 'form_type', 'name', 'email', 'email_mx_valid', 'phone', 'website',
    'message', 'interests', 'submission_count',
    'classification', 'classification_confidence', 'classification_reason', 'human_classification', 'classified_at',
    'status', 'review_note', 'reviewed_by_user_id', 'reviewed_at',
    'generated_reply', 'final_reply', 'generation_reason', 'generated_at', 'replied_at',
    'reply_attachments', 'raw_payload',
])]
class Prospect extends Model
{
    /** @use HasFactory<ProspectFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'email_mx_valid' => 'boolean',
            'interests' => 'array',
            'classification' => ProspectClassification::class,
            'classification_confidence' => 'integer',
            'human_classification' => ProspectClassification::class,
            'classified_at' => 'datetime',
            'status' => ProspectStatus::class,
            'reviewed_at' => 'datetime',
            'generated_at' => 'datetime',
            'replied_at' => 'datetime',
            'reply_attachments' => 'array',
            'raw_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Prospect $prospect): void {
            if ($prospect->status === ProspectStatus::PendingClassification) {
                ClassifyProspect::dispatch($prospect->id);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * Excludes spam: a human's call when there is one, otherwise the AI's,
     * so a lead a reviewer rescued from AI spam stays visible. Shared
     * between the Prospects table and any other admin view listing
     * prospects, so the definition of "spam" can't drift between them.
     */
    #[Scope]
    protected function notSpam(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query
                ->where('human_classification', '!=', ProspectClassification::Spam->value)
                ->orWhere(function (Builder $query): void {
                    $query
                        ->whereNull('human_classification')
                        ->where(function (Builder $query): void {
                            $query
                                ->whereNull('classification')
                                ->orWhere('classification', '!=', ProspectClassification::Spam->value);
                        });
                });
        });
    }

    /**
     * Moves the prospect to a new pipeline stage and fires whatever that
     * transition implies — Valid queues a reply draft, Replied sends the
     * actual email — so reviewers never have to trigger those by hand.
     */
    public function markStatus(ProspectStatus $status, ?int $reviewerId = null, ?string $note = null): bool
    {
        $updated = $this->update([
            'status' => $status,
            'review_note' => $note ?? $this->review_note,
            'reviewed_by_user_id' => $reviewerId ?? $this->reviewed_by_user_id,
            'reviewed_at' => now(),
            'human_classification' => $this->humanClassificationFor($status) ?? $this->human_classification,
        ]);

        if (! $updated) {
            return false;
        }

        if ($status === ProspectStatus::Valid) {
            $this->update(['status' => ProspectStatus::DraftGenerating]);
            GenerateProspectReply::dispatch($this->id);
        }

        if ($status === ProspectStatus::Replied) {
            $this->sendReply();
        }

        return true;
    }

    /**
     * Sets the pipeline status by hand, deliberately bypassing markStatus():
     * nothing is generated, classified or emailed. The admin has already
     * replied through another channel (such as Gmail) and only needs the
     * table to reflect it, so reply fields and classification stay untouched.
     */
    public function overrideStatus(ProspectStatus $status, ?int $reviewerId): bool
    {
        $updated = $this->update([
            'status' => $status,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        if ($updated) {
            RecordPipelineStatusChanged::dispatch('prospects-pipeline');
        }

        return $updated;
    }

    /**
     * The classification decisions a human actually makes (Valid/Spam/
     * Unanswerable) share their values with ProspectClassification, so the
     * human's call can be recorded permanently here — unlike `status`, which
     * keeps advancing through the reply pipeline after a Valid decision.
     */
    private function humanClassificationFor(ProspectStatus $status): ?ProspectClassification
    {
        return match ($status) {
            ProspectStatus::Valid => ProspectClassification::Valid,
            ProspectStatus::Spam => ProspectClassification::Spam,
            ProspectStatus::Unanswerable => ProspectClassification::Unanswerable,
            default => null,
        };
    }

    private function sendReply(): void
    {
        if ($this->email === null || $this->final_reply === null) {
            return;
        }

        Mail::to($this->email)->send(new ProspectReplyMail($this));
        $this->update(['replied_at' => now()]);
    }
}
