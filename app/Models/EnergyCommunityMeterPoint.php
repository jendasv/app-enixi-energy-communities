<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnergyCommunityMeterPointState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The registration (energy_community_meter_point). Its own model, not a bare
 * pivot — it carries a state machine and validity period that drive BR-7
 * through BR-10, not just a many-to-many link.
 */
class EnergyCommunityMeterPoint extends Model
{
    use HasFactory;

    protected $table = 'energy_community_meter_point';

    protected $fillable = [
        'energy_community_id',
        'meter_point_id',
        'state',
        'from_date',
        'to_date',
        'consent_date',
        'status_code',
    ];

    protected function casts(): array
    {
        return [
            'state' => EnergyCommunityMeterPointState::class,
            'from_date' => 'date',
            'to_date' => 'date',
            'consent_date' => 'date',
            'status_code' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EnergyCommunity, $this>
     */
    public function energyCommunity(): BelongsTo
    {
        return $this->belongsTo(EnergyCommunity::class);
    }

    /**
     * @return BelongsTo<MeterPoint, $this>
     */
    public function meterPoint(): BelongsTo
    {
        return $this->belongsTo(MeterPoint::class);
    }

    /**
     * BR-7: registrations in these states hold a metering point's period
     * against overlap with any other registration, across all communities.
     */
    public function scopeBlocking(Builder $query): Builder
    {
        $blockingStates = array_filter(
            EnergyCommunityMeterPointState::cases(),
            fn (EnergyCommunityMeterPointState $state) => $state->blocks(),
        );

        return $query->whereIn('state', array_column($blockingStates, 'value'));
    }
}
