<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case DemoScheduled = 'demo_scheduled';
    case ProposalSent = 'proposal_sent';
    case Converted = 'converted';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::DemoScheduled => 'Demo Scheduled',
            self::ProposalSent => 'Proposal Sent',
            self::Converted => 'Converted',
            self::Lost => 'Lost',
        };
    }
}
