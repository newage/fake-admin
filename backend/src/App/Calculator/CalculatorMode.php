<?php

declare(strict_types=1);

namespace App\Calculator;

enum CalculatorMode: string
{
    /** The result is computed during the request. */
    case Live = 'live';
    /** The result is stored in `calc_results`, written by a worker; nothing is computed during the request. */
    case Cached = 'cached';
}
