<?php

declare(strict_types=1);

namespace App\Enums;

enum EnergyCommunityState: string
{
    case New = 'new';
    case Activated = 'activated';
    case Rejected = 'rejected';
}
