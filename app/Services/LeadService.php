<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\Product;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function create(array $data, ?User $actor = null): Lead
    {
        return DB::transaction(function () use ($data, $actor): Lead {
            $lead = Lead::create($data);

            $source = (string) ($data['source'] ?? 'unknown');
            $description = $actor !== null
                ? 'Lead created manually'
                : 'Lead submitted from '.$this->formatSource($source);

            $this->logActivity($lead, $description, $actor, 'created', [
                'source' => $source,
            ]);

            return $lead;
        });
    }

    public function ensureLostHasRemarks(string $status, ?string $remarks, Lead $lead, ?string $rejectionReason = null): void
    {
        if ($status !== LeadStatus::Lost->value) {
            return;
        }

        $value = $remarks ?? $rejectionReason ?? $lead->remarks ?? $lead->rejection_reason;

        if (blank(trim((string) $value))) {
            throw ValidationException::withMessages([
                'remarks' => 'Remarks are required when marking a lead as Lost.',
            ]);
        }
    }

    public function updateStatus(Lead $lead, LeadStatus $status, ?User $actor = null, ?string $reason = null): Lead
    {
        $previous = $lead->status;

        if ($previous === $status) {
            return $lead;
        }

        $lead->update([
            'status' => $status,
            'rejection_reason' => $status === LeadStatus::Lost ? $reason : $lead->rejection_reason,
            'converted_at' => $status === LeadStatus::Converted ? now() : $lead->converted_at,
        ]);

        $description = "Status changed from {$previous->label()} to {$status->label()}";

        $properties = [
            'from' => $previous->value,
            'to' => $status->value,
        ];

        if ($status === LeadStatus::Lost && $reason) {
            $description .= " (reason: {$reason})";
            $properties['reason'] = $reason;
        }

        $this->logActivity($lead, $description, $actor, 'status_changed', $properties);

        return $lead->fresh();
    }

    public function assign(Lead $lead, ?int $userId, ?User $actor = null): Lead
    {
        if ($lead->assigned_to === $userId) {
            return $lead->fresh(['assignee']);
        }

        $assignee = $userId !== null ? User::query()->find($userId) : null;
        $lead->update(['assigned_to' => $userId]);

        $description = $assignee !== null
            ? "Assigned to {$assignee->name}"
            : 'Lead unassigned';

        $this->logActivity($lead, $description, $actor, 'assigned', [
            'assigned_to' => $userId,
            'assignee_name' => $assignee?->name,
        ]);

        return $lead->fresh(['assignee']);
    }

    public function markTrial(Lead $lead, ?User $actor = null): Lead
    {
        if (in_array($lead->status, [LeadStatus::Trial, LeadStatus::Converted], true)) {
            return $lead;
        }

        return $this->updateStatus($lead, LeadStatus::Trial, $actor);
    }

    public function updateDetails(Lead $lead, array $data, ?User $actor = null): Lead
    {
        $changes = [];

        if (array_key_exists('product', $data)) {
            $newProduct = $data['product'] !== null ? Product::from($data['product']) : null;
            $currentProduct = $lead->product;

            if ($newProduct !== $currentProduct) {
                $changes[] = $newProduct !== null
                    ? 'Product interest set to '.$newProduct->label()
                    : 'Product interest cleared';
            }
        }

        if (array_key_exists('remarks', $data) && $data['remarks'] !== $lead->remarks) {
            $changes[] = 'Remarks updated';
        }

        if (array_key_exists('name', $data) && $data['name'] !== $lead->name) {
            $changes[] = 'Name updated';
        }

        if (array_key_exists('email', $data) && $data['email'] !== $lead->email) {
            $changes[] = 'Email updated';
        }

        if (array_key_exists('mobile', $data) && $data['mobile'] !== $lead->mobile) {
            $changes[] = 'Mobile updated';
        }

        if (array_key_exists('company_name', $data) && $data['company_name'] !== $lead->company_name) {
            $changes[] = 'Company name updated';
        }

        if (array_key_exists('city', $data) && $data['city'] !== $lead->city) {
            $changes[] = 'City updated';
        }

        if (array_key_exists('state', $data) && $data['state'] !== $lead->state) {
            $changes[] = 'State updated';
        }

        if ($changes === []) {
            return $lead;
        }

        $lead->update($data);

        $this->logActivity($lead, implode('; ', $changes), $actor, 'updated', [
            'fields' => $changes,
        ]);

        return $lead->fresh();
    }

    public function scheduleFollowUp(Lead $lead, ?Carbon $at, ?User $actor = null): Lead
    {
        $current = $lead->next_follow_up_at;

        if (($current === null && $at === null)
            || ($current !== null && $at !== null && $current->equalTo($at))) {
            return $lead;
        }

        $lead->update(['next_follow_up_at' => $at]);

        $description = $at === null
            ? 'Next follow-up cleared'
            : 'Next follow-up scheduled for '.$at->format('d M Y');

        $this->logActivity($lead, $description, $actor, 'follow_up_scheduled', [
            'next_follow_up_at' => $at?->toIso8601String(),
        ]);

        return $lead->fresh();
    }

    private function logActivity(Lead $lead, string $description, ?User $actor, string $event, array $properties = []): void
    {
        activity('lead')
            ->performedOn($lead)
            ->causedBy($actor)
            ->event($event)
            ->withProperties($properties)
            ->log($description);
    }

    private function formatSource(string $source): string
    {
        return match ($source) {
            'website_demo' => 'Website demo form',
            'website_contact' => 'Website contact form',
            'website_partner' => 'Website partner form',
            'website_other' => 'Website (other)',
            'fleetmores_com_demo' => 'FleetMores.com demo form',
            'fleetmores_in_demo' => 'FleetMores.in demo form',
            'fleetezee_com_demo' => 'Fleetezee.com demo form',
            'fleetezee_in_demo' => 'Fleetezee.in demo form',
            'manual' => 'Manual entry',
            default => $source,
        };
    }
}
