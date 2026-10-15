<?php

declare(strict_types=1);

namespace App\Enums;

enum LolSubscriptionMode: string
{
    case All = 'all';
    case Teams = 'teams';
}
