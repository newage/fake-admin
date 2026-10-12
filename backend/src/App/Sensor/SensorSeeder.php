<?php

declare(strict_types=1);

namespace App\Sensor;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

use function array_merge;
use function count;
use function implode;
use function intdiv;
use function min;
use function sprintf;

/**
 * Fake sensors for development. Data is deterministic by number, so a repeated run
 * duplicates nothing (unique `code`). Sensor secrets are not generated.
 */
final class SensorSeeder
{
    private const TYPES = [
        ['temperature', '°C', 'Temperature'],
        ['humidity', '%', 'Humidity'],
        ['pressure', 'hPa', 'Pressure'],
        ['co2', 'ppm', 'CO2'],
        ['voltage', 'V', 'Voltage'],
        ['light', 'lx', 'Light'],
    ];
    private const LOCATIONS = ['Warehouse A', 'Warehouse B', 'Greenhouse', 'Office', 'Server room', 'Yard', 'Cold storage'];
    private const STATUSES  = ['active', 'active', 'active', 'active', 'inactive', 'maintenance'];
    private const BATCH     = 500;

    public function __construct(private readonly Connection $db)
    {
    }

    /** @return int number of rows actually created */
    public function seed(int $count): int
    {
        $now      = new DateTimeImmutable('now');
        $inserted = 0;

        for ($start = 1; $start <= $count; $start += self::BATCH) {
            $end    = min($start + self::BATCH - 1, $count);
            $rows   = [];
            $params = [];
            for ($n = $start; $n <= $end; ++$n) {
                $rows[]   = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
                $params[] = $this->row($n, $now);
            }
            $inserted += $this->db->executeStatement(
                'INSERT IGNORE INTO sensors (code, name, type, unit, device_id, location, status, created_at, last_seen_at) VALUES '
                . implode(', ', $rows),
                array_merge(...$params),
            );
        }

        return $inserted;
    }

    /** @return list<string|null> */
    private function row(int $n, DateTimeImmutable $now): array
    {
        [$type, $unit, $label] = self::TYPES[$n % count(self::TYPES)];
        $status                = self::STATUSES[$n % count(self::STATUSES)];

        return [
            sprintf('SN-%06d', $n),
            sprintf('%s sensor %d', $label, $n),
            $type,
            $unit,
            sprintf('DEV-%05d', intdiv($n - 1, 4) + 1),
            self::LOCATIONS[$n % count(self::LOCATIONS)],
            $status,
            $now->modify(sprintf('-%d days', ($n * 7) % 730))->format('Y-m-d H:i:s'),
            $status === 'active' ? $now->modify(sprintf('-%d minutes', ($n * 13) % 600))->format('Y-m-d H:i:s') : null,
        ];
    }
}
