<?php

namespace App\Policies;

use App\Models\Organisation;
use App\Models\User;

class OrganisationPolicy
{
    public function view(User $user, Organisation $organisation): bool
    {
        return $organisation->status === 'active' && $user->organisations()->whereKey($organisation->id)->exists();
    }

    public function update(User $user, Organisation $organisation): bool
    {
        return $this->view($user, $organisation) && in_array($user->organisations()->whereKey($organisation->id)->first()?->pivot->role, ['owner', 'administrator'], true);
    }

    public function manageMembers(User $user, Organisation $organisation): bool
    {
        return $this->view($user, $organisation) && $user->organisations()->whereKey($organisation->id)->first()?->pivot->role === 'owner';
    }

    public function manageCampaigns(User $user, Organisation $organisation): bool
    {
        return $this->view($user, $organisation) && in_array($user->organisations()->whereKey($organisation->id)->first()?->pivot->role, ['owner', 'administrator', 'campaign_manager'], true);
    }
}
