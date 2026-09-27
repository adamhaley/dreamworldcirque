<?php

namespace App\Notifications;

use App\Enums\ProspectClassification;
use App\Filament\Resources\Prospects\ProspectResource;
use App\Models\Prospect;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewContactSubmission extends Notification implements ShouldQueue
{
    use Queueable;

    private const HighConfidenceSpamThreshold = 95;

    public function __construct(public Prospect $prospect) {}

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $prospect = $this->prospect->fresh();

        return $prospect !== null
            && ! ($prospect->classification === ProspectClassification::Spam
                && ($prospect->classification_confidence ?? 0) >= self::HighConfidenceSpamThreshold);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Dream World Cirque inquiry')
            ->greeting('New contact form submission')
            ->line('Name: '.$this->prospect->name)
            ->line('Email: '.$this->prospect->email)
            ->lineIf($this->prospect->phone !== null, 'Phone: '.$this->prospect->phone)
            ->line('Form: '.$this->prospect->form_type)
            ->line('Message: '.Str::limit(Str::squish((string) $this->prospect->message), 240))
            ->action('Review inquiry', ProspectResource::getUrl(parameters: [
                'tableAction' => 'review',
                'tableActionRecord' => $this->prospect,
            ], panel: 'admin'));
    }
}
