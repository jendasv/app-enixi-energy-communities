<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnergyCommunityState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnergyCommunity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ecid',
        'name',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'state' => EnergyCommunityState::class,
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'energy_community_user')
            ->withPivot('role')
            ->withTimestamps()
            ->using(EnergyCommunityUser::class)
            ->as('membership');
    }

    /**
     * @return HasMany<EnergyCommunityMeterPoint, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EnergyCommunityMeterPoint::class);
    }
}
