<?php

declare(strict_types=1);

namespace App\Enums;

enum CommunityRole: string
{
    case Manager = 'manager';
    case Member = 'member';
}
