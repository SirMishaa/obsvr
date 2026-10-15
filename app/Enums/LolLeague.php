<?php

declare(strict_types=1);

namespace App\Enums;

enum LolLeague: string
{
    case Worlds = 'worlds';
    case Msi = 'msi';
    case FirstStand = 'first_stand';
    case Lck = 'lck';
    case Lec = 'lec';

    public function leagueId(): string
    {
        return match ($this) {
            self::Worlds => '98767975604431411',
            self::Msi => '98767991325878492',
            self::FirstStand => '113464388705111224',
            self::Lck => '98767991310872058',
            self::Lec => '98767991302996019',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Worlds => 'Worlds',
            self::Msi => 'MSI',
            self::FirstStand => 'First Stand',
            self::Lck => 'LCK',
            self::Lec => 'LEC',
        };
    }

    public function image(): string
    {
        return match ($this) {
            self::Worlds => 'https://static.lolesports.com/leagues/1592594612171_WorldsDarkBG.png',
            self::Msi => 'https://static.lolesports.com/leagues/1592594634248_MSIDarkBG.png',
            self::FirstStand => 'https://static.lolesports.com/leagues/1740042025201_RG_LOL_FIRST_STAND_LOGO_VOLT_ALPHA.png',
            self::Lck => 'https://static.lolesports.com/leagues/lck-color-on-black.png',
            self::Lec => 'https://static.lolesports.com/leagues/1592516184297_LEC-01-FullonDark.png',
        };
    }
}
