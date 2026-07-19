<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadCapturedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Lead $lead,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $leadUrl = route('leads.show', $this->lead);
        $trafficSource = $this->lead->utm_source
            ?: ($this->referrerHost() ?: 'Direct');
        $trafficMedium = $this->lead->utm_medium ?: '—';

        return (new MailMessage)
            ->subject('New website lead: '.$this->lead->name)
            ->view('emails.leads.captured', [
                'subject' => 'New website lead: '.$this->lead->name,
                'lead' => $this->lead,
                'leadUrl' => $leadUrl,
                'trafficSource' => $trafficSource,
                'trafficMedium' => $trafficMedium,
            ]);
    }

    private function referrerHost(): ?string
    {
        $referrer = $this->lead->referrer;
        if (! filled($referrer)) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}
