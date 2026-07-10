<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PartnerOnboardingInvitationNotification extends Notification implements ShouldQueue
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
        $expiryDays = (int) config('partner_onboarding.invitation_ttl_days', 7);

        return (new MailMessage)
            ->subject('Complete your TruckBill Sales Partner onboarding')
            ->view('emails.partner-onboarding.invite', [
                'subject' => 'Complete your TruckBill Sales Partner onboarding',
                'name' => $this->name,
                'url' => $this->url,
                'expiryDays' => $expiryDays,
            ]);
    }
}
