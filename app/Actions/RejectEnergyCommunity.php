<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\EnergyCommunityState;
use App\Models\EnergyCommunity;
use Illuminate\Support\Facades\DB;

/**
 * BR-13: state -> rejected and every blocking registration ended per BR-9,
 * atomically — if any part fails, nothing is written.
 */
class RejectEnergyCommunity
{
    public function __construct(
        private readonly TransitionRegistration $transitionRegistration,
    ) {}

    public function handle(EnergyCommunity $community): EnergyCommunity
    {
        return DB::transaction(function () use ($community) {
            $blocking = $community->registrations()->blocking()->lockForUpdate()->get();

            foreach ($blocking as $registration) {
                $target = $registration->state->deletionTarget();

                if ($target !== null) {
                    $this->transitionRegistration->handle($registration, $target, null);
                }
            }

            $community->update(['state' => EnergyCommunityState::Rejected]);

            return $community->refresh();
        });
    }
}
