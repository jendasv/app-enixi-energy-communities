<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CommunityRole;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\User;

class EnergyCommunityMeterPointPolicy
{
    /**
     * BR-9/BR-10: a manager of the registration's community (or an admin)
     * triggers transitions by hand — including the "delete" endpoint, which
     * is itself just a transition (BR-10).
     */
    public function transition(User $user, EnergyCommunityMeterPoint $registration): bool
    {
        return $user->is_admin || $registration->energyCommunity->users()
            ->whereKey($user->id)
            ->wherePivot('role', CommunityRole::Manager)
            ->exists();
    }
}
