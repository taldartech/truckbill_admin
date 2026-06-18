<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnboardingWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $name,
        private readonly string $loginUrl,
        private readonly string $productLabel,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to TruckBill')
            ->view('emails.onboarding.welcome', [
                'subject' => 'Welcome to TruckBill',
                'name' => $this->name,
                'loginUrl' => $this->loginUrl,
                'productLabel' => $this->productLabel,
            ]);
    }
}
