<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        if (! $user->can('leads.view')) {
            return false;
        }

        if ($user->hasRole('Sales Executive')) {
            return $lead->assigned_to === $user->id;
        }

        if ($user->hasRole('Sales Manager')) {
            return $lead->assigned_to === $user->id
                || $lead->assignee?->manager_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('leads.create');
    }

    public function update(User $user, Lead $lead): bool
    {
        if (! $user->can('leads.update')) {
            return false;
        }

        if ($user->hasRole('Support')) {
            return false;
        }

        return $this->view($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->can('leads.delete') && $this->view($user, $lead);
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->can('leads.assign') && $this->view($user, $lead);
    }

    public function invite(User $user, Lead $lead): bool
    {
        return $user->can('leads.invite') && $this->view($user, $lead);
    }
}
