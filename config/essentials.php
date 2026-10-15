<?php

declare(strict_types=1);

use NunoMaduro\Essentials\Configurables\ShouldBeStrict;

return [

    /*
    |--------------------------------------------------------------------------
    | Should Be Strict
    |--------------------------------------------------------------------------
    |
    | Disabled here and applied by AppServiceProvider instead: strict outside
    | production, while production only logs lazy-loading violations rather
    | than failing requests. Other essentials keep their package defaults.
    |
    */

    ShouldBeStrict::class => false,

];
