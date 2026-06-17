<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadService
{
    public function create(array $data, ?User $actor = null): Lead
    {
        return DB::transaction(function () use ($data, $actor): Lead {
            $lead = Lead::create($data);

            activity('lead')
                ->performedOn($lead)
                ->causedBy($actor)
                ->withProperties(['action' => 'created'])
                ->log('Lead created');

            return $lead;
        });
    }

    public function updateStatus(Lead $lead, LeadStatus $status, ?User $actor = null, ?string $reason = null): Lead
    {
        $previous = $lead->status;
        $lead->update([
            'status' => $status,
            'rejection_reason' => $status === LeadStatus::Lost ? $reason : $lead->rejection_reason,
            'converted_at' => $status === LeadStatus::Converted ? now() : $lead->converted_at,
        ]);

        activity('lead')
            ->performedOn($lead)
            ->causedBy($actor)
            ->withProperties(['from' => $previous->value, 'to' => $status->value])
            ->log('Lead status changed');

        return $lead->fresh();
    }

    public function assign(Lead $lead, ?int $userId, ?User $actor = null): Lead
    {
        $lead->update(['assigned_to' => $userId]);

        activity('lead')
            ->performedOn($lead)
            ->causedBy($actor)
            ->withProperties(['assigned_to' => $userId])
            ->log('Lead assigned');

        return $lead->fresh(['assignee']);
    }
}
