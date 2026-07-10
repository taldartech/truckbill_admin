<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalesPartnerWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $user,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replyTo = (string) config('partner_onboarding.documents_reply_to');
        $loginUrl = route('login');

        return (new MailMessage)
            ->subject('Welcome to TruckBill — please share your documents')
            ->replyTo($replyTo)
            ->view('emails.partner-onboarding.welcome', [
                'subject' => 'Welcome to TruckBill — please share your documents',
                'name' => $this->user->name,
                'loginUrl' => $loginUrl,
                'replyTo' => $replyTo,
            ]);
    }
}
