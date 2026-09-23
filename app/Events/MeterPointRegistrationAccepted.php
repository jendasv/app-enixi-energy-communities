<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EnergyCommunityMeterPoint;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeterPointRegistrationAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly EnergyCommunityMeterPoint $registration,
    ) {}
}
