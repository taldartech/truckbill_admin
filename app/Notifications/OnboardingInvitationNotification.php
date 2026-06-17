<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnboardingInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Complete your TruckBill onboarding')
            ->line('You are invited to complete onboarding for your TruckBill account.')
            ->action('Start Onboarding', $this->url)
            ->line('This link expires in '.config('onboarding.invitation_ttl_days', 7).' days.');
    }
}
