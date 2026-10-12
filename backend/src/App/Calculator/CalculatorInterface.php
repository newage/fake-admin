<?php

declare(strict_types=1);

namespace App\Calculator;

interface CalculatorInterface
{
    /** Unique name; the key in the registry and in `calc_results.calculator`. */
    public function name(): string;

    /** The calculator declares its own mode; the API client does not choose it. */
    public function mode(): CalculatorMode;

    /**
     * Calculation over the sensor's raw data within a window (e.g. `1h`, `24h`).
     * Called during the request for `live` and by a worker for `cached`.
     */
    public function calculate(int $sensorId, string $window): float;
}
