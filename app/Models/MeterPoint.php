<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnergyDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeterPoint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'user_id',
        'energy_direction',
        'grid_operator_id',
    ];

    protected function casts(): array
    {
        return [
            'energy_direction' => EnergyDirection::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * grid_operator_id holds grid_operators.identifier, not grid_operators.id —
     * see the "watch this one" note in the assignment (section 3) and NOTES.md.
     *
     * @return BelongsTo<GridOperator, $this>
     */
    public function gridOperator(): BelongsTo
    {
        return $this->belongsTo(GridOperator::class, 'grid_operator_id', 'identifier');
    }

    /**
     * @return HasMany<EnergyCommunityMeterPoint, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EnergyCommunityMeterPoint::class);
    }
}
