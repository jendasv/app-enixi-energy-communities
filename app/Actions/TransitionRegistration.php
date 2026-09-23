<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\EnergyCommunityMeterPointState;
use App\Events\MeterPointRegistrationAccepted;
use App\Models\EnergyCommunityMeterPoint;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * BR-9: applies a state transition. The `WHERE state = <state read before
 * this call>` guard makes the write race-safe (BR-8) — if another request
 * changed the state first, affected rows is 0 and we surface a 409 instead
 * of silently overwriting a state we didn't validate against.
 */
class TransitionRegistration
{
    public function handle(
        EnergyCommunityMeterPoint $registration,
        EnergyCommunityMeterPointState $target,
        ?int $statusCode,
    ): EnergyCommunityMeterPoint {
        $fromState = $registration->state;

        $attributes = ['state' => $target->value];

        if ($target === EnergyCommunityMeterPointState::Error) {
            $attributes['status_code'] = $statusCode;
        }

        if ($target->isTerminal()) {
            $attributes['to_date'] = $this->closingToDate($registration);
        }

        $affected = EnergyCommunityMeterPoint::query()
            ->whereKey($registration->id)
            ->where('state', $fromState->value)
            ->update($attributes);

        if ($affected === 0) {
            throw new ConflictHttpException(
                'The registration state changed before this transition could be applied.',
            );
        }

        $registration = $registration->refresh();

        if ($target === EnergyCommunityMeterPointState::Accepted) {
            MeterPointRegistrationAccepted::dispatch($registration);
        }

        return $registration;
    }

    /**
     * BR-9: to_date becomes today, unless it already holds an earlier date,
     * and never a date before from_date.
     */
    private function closingToDate(EnergyCommunityMeterPoint $registration): string
    {
        $today = CarbonImmutable::today();

        $toDate = $registration->to_date !== null && $registration->to_date->lt($today)
            ? $registration->to_date
            : $today;

        return $toDate->max($registration->from_date)->toDateString();
    }
}
