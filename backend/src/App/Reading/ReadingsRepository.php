<?php

declare(strict_types=1);

namespace App\Reading;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\TableNotFoundException;

use function array_slice;
use function count;
use function str_replace;

/**
 * Reads raw readings from `sensor_readings(sensor_id, ts, value, ...)`. The table is created by consumers;
 * the API depends only on these three columns and the uniqueness of (sensor_id, ts).
 */
final class ReadingsRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function sensorExists(int $sensorId): bool
    {
        return $this->db->fetchOne('SELECT 1 FROM sensors WHERE id = ?', [$sensorId]) !== false;
    }

    /**
     * @return array{items: list<array{ts: string, value: float}>, has_more: bool}
     * @throws ReadingsUnavailable
     */
    public function page(int $sensorId, ReadingsQuery $query): array
    {
        try {
            // No COUNT(*): the table is large and grows fast, so one row more than the page size
            $rows = $this->db->fetchAllAssociative(
                'SELECT ts, value FROM sensor_readings
                  WHERE sensor_id = ? AND ts >= ? AND ts <= ?
                  ORDER BY ts ASC
                  LIMIT ' . ($query->pageSize + 1) . ' OFFSET ' . $query->offset(),
                [$sensorId, $query->from->format('Y-m-d H:i:s'), $query->to->format('Y-m-d H:i:s')],
            );
        } catch (TableNotFoundException | ConnectionException $e) {
            throw ReadingsUnavailable::because($e);
        }

        $items = [];
        foreach (array_slice($rows, 0, $query->pageSize) as $row) {
            $items[] = ['ts' => str_replace(' ', 'T', (string) $row['ts']) . 'Z', 'value' => (float) $row['value']];
        }

        return ['items' => $items, 'has_more' => count($rows) > $query->pageSize];
    }
}
