<?php

namespace App\Notifications;

use App\Filament\Resources\Prospects\ProspectResource;
use App\Models\Prospect;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ProspectPipelineFailed extends Notification
{
    /**
     * Sent synchronously from a job's failed() hook, so the alert does not
     * depend on the same queue that just gave up.
     */
    public function __construct(public Prospect $prospect, public string $stage, public string $reason) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('Dream World Cirque prospect '.$this->stage.' failed: '.$this->prospect->name)
            ->greeting('Prospect '.$this->stage.' failed')
            ->line('The AI '.$this->stage.' for '.$this->prospect->name.' gave up after retrying.')
            ->line('Reason: '.Str::limit($this->reason, 300))
            ->line('Check storage/logs/laravel.log on the server for the underlying error (for example an expired OpenAI key).')
            ->action('Open prospect', ProspectResource::getUrl(parameters: [
                'tableAction' => 'review',
                'tableActionRecord' => $this->prospect,
            ], panel: 'admin'));
    }
}
