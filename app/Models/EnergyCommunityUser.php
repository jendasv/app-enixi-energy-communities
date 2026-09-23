<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommunityRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The energy_community_user pivot — carries its own attribute (role), so it
 * gets a dedicated pivot model instead of a bare withPivot() array.
 */
class EnergyCommunityUser extends Pivot
{
    protected $table = 'energy_community_user';

    protected function casts(): array
    {
        return [
            'role' => CommunityRole::class,
        ];
    }
}
