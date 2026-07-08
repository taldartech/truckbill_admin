<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\Product;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

    public function markConverted(Lead $lead, ?User $actor = null): Lead
    {
        if ($lead->status === LeadStatus::Converted) {
            return $lead;
        }

        return $this->updateStatus($lead, LeadStatus::Converted, $actor);
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

        if ($changes === []) {
            return $lead;
        }

        $lead->update($data);

        $this->logActivity($lead, implode('; ', $changes), $actor, 'updated', [
            'fields' => $changes,
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
            'manual' => 'Manual entry',
            default => $source,
        };
    }
}
