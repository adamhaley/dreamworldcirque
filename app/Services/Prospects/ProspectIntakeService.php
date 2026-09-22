<?php

declare(strict_types=1);

namespace App\Services\Prospects;

use App\Enums\ProspectStatus;
use App\Models\Prospect;

/**
 * The single path every live intake channel creates a Prospect through --
 * the chatbot fallback and the contact forms both call this, so the
 * source/form_type conventions and default status live in one place
 * instead of being repeated (and drifting) per channel.
 */
class ProspectIntakeService
{
    public function record(string $source, string $formType, ProspectIntakeData $data): Prospect
    {
        return Prospect::query()->create([
            'source' => $source,
            'form_type' => $formType,
            'submitted_at' => now(),
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'message' => $data->message,
            'interests' => $data->interests !== [] ? $data->interests : null,
            'raw_payload' => $data->rawPayload,
            // Set explicitly rather than left to the column's DB-level
            // default: Eloquent's create() doesn't refetch the row after
            // insert, so an omitted status would stay null on this
            // in-memory instance -- and Prospect::booted()'s created hook
            // checks that same in-memory value to decide whether to
            // dispatch ClassifyProspect. Relying on the default would
            // silently skip classification entirely.
            'status' => ProspectStatus::PendingClassification,
        ]);
    }
}
