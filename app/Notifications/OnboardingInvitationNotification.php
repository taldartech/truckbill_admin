<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnboardingInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $name,
        private readonly string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiryDays = (int) config('onboarding.invitation_ttl_days', 7);

        return (new MailMessage)
            ->subject('Your TruckBill Onboarding Link')
            ->view('emails.onboarding.invite', [
                'subject' => 'Your TruckBill Onboarding Link',
                'name' => $this->name,
                'url' => $this->url,
                'expiryDays' => $expiryDays,
            ]);
    }
}
