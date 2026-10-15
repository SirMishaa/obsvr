<?php

declare(strict_types=1);

namespace App\Enums;

enum LolNotificationType: string
{
    case Reminder = 'reminder';
    case Started = 'started';
}
