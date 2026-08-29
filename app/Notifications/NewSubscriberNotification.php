<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the admin/support inbox when a paid subscription starts.
 */
class NewSubscriberNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context  plan/interval/trial from Stripe
     */
    public function __construct(
        public User $user,
        public array $context = [],
    ) {}

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
            ->subject("[{$appName}] New paying subscriber: {$this->user->email}")
            ->text('emails.new-subscriber', [
                'user' => $this->user,
                'context' => $this->context,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'plan' => $this->context['plan'] ?? null,
        ];
    }
}
