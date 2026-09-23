<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EnergyCommunityMeterPointState;
use App\Enums\EnergyDirection;
use App\Models\EnergyCommunity;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class EcRegistrationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ec:registrations {ecid} {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List metering points whose registration in a community was accepted and valid on a given day (default: today), generation first.';

    public function handle(): int
    {
        $community = EnergyCommunity::query()->where('ecid', $this->argument('ecid'))->first();

        if ($community === null) {
            $this->error("No energy community found with ecid [{$this->argument('ecid')}].");

            return self::FAILURE;
        }

        $date = $this->option('date') !== null
            ? CarbonImmutable::parse($this->option('date'))
            : CarbonImmutable::today();

        $registrations = $community->registrations()
            ->where('state', EnergyCommunityMeterPointState::Accepted)
            ->where('from_date', '<=', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date', '>=', $date->toDateString()))
            ->with('meterPoint')
            ->get()
            ->sortBy(fn ($registration) => $registration->meterPoint->energy_direction === EnergyDirection::Generation ? 0 : 1)
            ->values();

        if ($registrations->isEmpty()) {
            $this->info("No accepted registrations valid on {$date->toDateString()}.");

            return self::SUCCESS;
        }

        $this->table(
            ['Meter Point', 'Direction', 'From', 'To'],
            $registrations->map(fn ($registration) => [
                $registration->meterPoint->name,
                $registration->meterPoint->energy_direction->value,
                $registration->from_date->toDateString(),
                $registration->to_date?->toDateString() ?? 'open',
            ]),
        );

        return self::SUCCESS;
    }
}
