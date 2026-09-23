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
        return $this->isManager($user, $energyCommunity);
    }

    /**
     * BR-5: only a manager of the community (or an admin) may register a
     * metering point into it. The "new or activated, never rejected" part
     * of BR-5 is a state check, not an authorization one — it lives in
     * RegisterMeterPointRequest instead.
     */
    public function registerMeterPoint(User $user, EnergyCommunity $energyCommunity): bool
    {
        return $this->isManager($user, $energyCommunity);
    }

    private function isManager(User $user, EnergyCommunity $energyCommunity): bool
    {
        return $user->is_admin || $energyCommunity->users()
            ->whereKey($user->id)
            ->wherePivot('role', CommunityRole::Manager)
            ->exists();
    }
}
