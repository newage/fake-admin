<?php

declare(strict_types=1);

namespace App\Calculator;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;

use function str_replace;

/**
 * The single entry point for a calculator result: the calculator itself determines the mode (`live`/`cached`).
 */
final class CalculatorResults
{
    public function __construct(
        private readonly CalculatorRegistry $registry,
        private readonly Connection $db,
    ) {
    }

    /**
     * @return CalculatedValue|null null only for `cached`, if the worker has not yet computed the value
     * @throws UnknownCalculator
     */
    public function get(string $calculator, int $sensorId, string $window): ?CalculatedValue
    {
        $instance = $this->registry->get($calculator);

        if ($instance->mode() === CalculatorMode::Live) {
            return new CalculatedValue(
                $instance->calculate($sensorId, $window),
                (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            );
        }

        $row = $this->db->fetchAssociative(
            'SELECT value, computed_at FROM calc_results WHERE calculator = ? AND sensor_id = ? AND time_window = ?',
            [$calculator, $sensorId, $window],
        );

        return $row === false ? null : new CalculatedValue((float) $row['value'], str_replace(' ', 'T', $row['computed_at']) . 'Z');
    }
}
