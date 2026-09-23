<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CommunityRole;
use App\Models\EnergyCommunity;
use App\Models\User;

class EnergyCommunityPolicy
{
    /**
     * GET /api/energy-communities/{energyCommunity} — members and admins
     * only. A non-member gets a 404 (visibility, not just permission), so
     * the controller checks this and aborts 404 rather than calling
     * $this->authorize() (which would 403).
     */
    public function view(User $user, EnergyCommunity $energyCommunity): bool
    {
        return $user->is_admin || $energyCommunity->users()->whereKey($user->id)->exists();
    }

    /**
     * BR-4: only a manager of the community (or an admin) may add users.
     */
    public function addUser(User $user, EnergyCommunity $energyCommunity): bool
    {
        return $user->is_admin || $energyCommunity->users()
            ->whereKey($user->id)
            ->wherePivot('role', CommunityRole::Manager)
            ->exists();
    }
}
