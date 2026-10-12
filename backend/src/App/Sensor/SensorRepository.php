<?php

declare(strict_types=1);

namespace App\Sensor;

use Doctrine\DBAL\Connection;

use function addcslashes;
use function implode;
use function str_replace;

final class SensorRepository
{
    /** Columns returned to the client: the sensor secret and key version are not included. */
    private const COLUMNS = 'id, code, name, type, unit, device_id, location, status, created_at, last_seen_at';

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(SensorListQuery $query): array
    {
        [$where, $params] = $this->filters($query);

        $total = (int) $this->db->fetchOne('SELECT COUNT(*) FROM sensors' . $where, $params);

        // The column name comes only from the SensorListQuery::SORTABLE whitelist, direction is asc|desc
        $sql = 'SELECT ' . self::COLUMNS . ' FROM sensors' . $where
            . " ORDER BY {$query->sort} {$query->order}, id {$query->order}"
            . ' LIMIT ' . $query->pageSize . ' OFFSET ' . $query->offset();

        $items = [];
        foreach ($this->db->fetchAllAssociative($sql, $params) as $row) {
            $items[] = [
                'id'           => (int) $row['id'],
                'code'         => $row['code'],
                'name'         => $row['name'],
                'type'         => $row['type'],
                'unit'         => $row['unit'],
                'device_id'    => $row['device_id'],
                'location'     => $row['location'],
                'status'       => $row['status'],
                'created_at'   => self::iso($row['created_at']),
                'last_seen_at' => $row['last_seen_at'] === null ? null : self::iso($row['last_seen_at']),
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    /** @return array{string, list<string>} */
    private function filters(SensorListQuery $query): array
    {
        $conditions = [];
        $params     = [];

        if ($query->name !== null) {
            $conditions[] = 'name LIKE ?';
            $params[]     = self::likePrefix($query->name);
        }
        if ($query->code !== null) {
            $conditions[] = 'code LIKE ?';
            $params[]     = self::likePrefix($query->code);
        }
        if ($query->createdFrom !== null) {
            $conditions[] = 'created_at >= ?';
            $params[]     = $query->createdFrom->format('Y-m-d H:i:s');
        }
        if ($query->createdTo !== null) {
            $conditions[] = 'created_at <= ?';
            $params[]     = $query->createdTo->format('Y-m-d H:i:s');
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $params];
    }

    /** Prefix search: `%`, `_` and `\` in the user's value are matched literally. */
    private static function likePrefix(string $value): string
    {
        return addcslashes($value, '%_\\') . '%';
    }

    private static function iso(string $dbDateTime): string
    {
        return str_replace(' ', 'T', $dbDateTime) . 'Z';
    }
}
