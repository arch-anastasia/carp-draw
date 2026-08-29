<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A contact-form submission, forwarded to the support inbox.
 */
class ContactMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{name: string, email: string, subject: string, message: string}  $submission
     */
    public function __construct(public array $submission) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('branding.name', config('app.name'));

        return (new MailMessage)
            ->subject("[{$appName}] Contact form: {$this->submission['subject']}")
            ->replyTo($this->submission['email'], $this->submission['name'])
            ->text('emails.contact-message', [
                'submission' => $this->submission,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->submission;
    }
}
