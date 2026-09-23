<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CommunityRole;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnergyCommunityMeterPointPolicy
{
    /**
     * BR-9/BR-10: a manager of the registration's community (or an admin)
     * triggers transitions by hand — including the "delete" endpoint, which
     * is itself just a transition (BR-10).
     *
     * Queries the pivot table directly instead of
     * $registration->energyCommunity->users()->wherePivot(...) — that property
     * access lazy-loads and *caches* the relation on $registration, and
     * TransitionRegistration's later ->refresh() call reloads every cached
     * relation along with the row itself, turning one avoidable check into a
     * second real query later in the same request. Measured with
     * DB::enableQueryLog(): this form is 1 query total; the property-access
     * form was 2 here plus 1 more at refresh() time. (wherePivot() itself only
     * works on an actual BelongsToMany relation instance, not inside a
     * whereHas() closure's subquery builder — tried that first, it silently
     * builds a broken WHERE clause instead of erroring at write time.)
     */
    public function transition(User $user, EnergyCommunityMeterPoint $registration): bool
    {
        return $user->is_admin || DB::table('energy_community_user')
            ->where('energy_community_id', $registration->energy_community_id)
            ->where('user_id', $user->id)
            ->where('role', CommunityRole::Manager->value)
            ->exists();
    }
}
