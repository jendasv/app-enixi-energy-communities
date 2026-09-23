<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\EnergyCommunityMeterPointState;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\MeterPoint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * BR-5 through BR-8: registers a metering point into a community. The
 * overlap check (BR-7) and the write happen in one locked transaction
 * (BR-8) — see Claude.md for what guarantee this actually gives on
 * MariaDB/InnoDB and its gap-locking limits for a meter point's first
 * registration.
 */
class RegisterMeterPointIntoCommunity
{
    public function handle(
        EnergyCommunity $community,
        MeterPoint $meterPoint,
        CarbonImmutable $fromDate,
        ?CarbonImmutable $toDate,
        CarbonImmutable $consentDate,
    ): EnergyCommunityMeterPoint {
        return DB::transaction(function () use ($community, $meterPoint, $fromDate, $toDate, $consentDate) {
            $blocking = EnergyCommunityMeterPoint::query()
                ->where('meter_point_id', $meterPoint->id)
                ->blocking()
                ->lockForUpdate()
                ->get();

            foreach ($blocking as $existing) {
                if ($this->periodsOverlap($existing, $fromDate, $toDate)) {
                    throw new ConflictHttpException(
                        "This metering point already has a blocking registration (#{$existing->id}) ".
                        'whose period overlaps the requested one.',
                    );
                }
            }

            return EnergyCommunityMeterPoint::create([
                'energy_community_id' => $community->id,
                'meter_point_id' => $meterPoint->id,
                'state' => EnergyCommunityMeterPointState::New,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'consent_date' => $consentDate,
            ]);
        });
    }

    /**
     * BR-7: a.from <= b.to (or b.to null) and b.from <= a.to (or a.to null).
     */
    private function periodsOverlap(
        EnergyCommunityMeterPoint $existing,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): bool {
        $existingStartsBeforeNewEnds = $newTo === null || $existing->from_date->lte($newTo);
        $newStartsBeforeExistingEnds = $existing->to_date === null || $newFrom->lte($existing->to_date);

        return $existingStartsBeforeNewEnds && $newStartsBeforeExistingEnds;
    }
}
