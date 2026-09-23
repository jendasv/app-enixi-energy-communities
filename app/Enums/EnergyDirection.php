<?php

declare(strict_types=1);

namespace App\Enums;

enum EnergyDirection: string
{
    case Generation = 'generation';
    case Consumption = 'consumption';
}
