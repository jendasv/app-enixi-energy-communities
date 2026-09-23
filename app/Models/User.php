<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Mirrors the migration's DB-level default. Without this, a freshly
     * created model never learns the column exists until it's re-fetched —
     * strict mode (see AppServiceProvider) then throws on ->is_admin instead
     * of silently returning null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * @return HasMany<MeterPoint, $this>
     */
    public function meterPoints(): HasMany
    {
        return $this->hasMany(MeterPoint::class);
    }

    /**
     * @return BelongsToMany<EnergyCommunity, $this>
     */
    public function energyCommunities(): BelongsToMany
    {
        return $this->belongsToMany(EnergyCommunity::class, 'energy_community_user')
            ->withPivot('role')
            ->withTimestamps()
            ->using(EnergyCommunityUser::class)
            ->as('membership');
    }
}
