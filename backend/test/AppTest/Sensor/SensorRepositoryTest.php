<?php

declare(strict_types=1);

namespace AppTest\Sensor;

use App\Sensor\SensorListQuery;
use App\Sensor\SensorRepository;
use App\Sensor\SensorSeeder;
use AppTest\Db\DatabaseTestCase;

use function array_column;
use function array_unique;
use function count;

final class SensorRepositoryTest extends DatabaseTestCase
{
    private SensorRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateUp();
        (new SensorSeeder($this->db))->seed(60);
        $this->repository = new SensorRepository($this->db);
    }

    public function testFirstPageAndTotal(): void
    {
        $result = $this->repository->paginate(SensorListQuery::fromParams(['page_size' => '25']));

        self::assertSame(60, $result['total']);
        self::assertCount(25, $result['items']);
    }

    public function testLastPageIsPartialAndPagesDoNotOverlap(): void
    {
        $seen = [];
        foreach ([1, 2, 3] as $page) {
            $result = $this->repository->paginate(SensorListQuery::fromParams(['page' => (string) $page, 'page_size' => '25', 'sort' => 'name']));
            $seen   = [...$seen, ...array_column($result['items'], 'id')];
        }

        self::assertCount(60, $seen);
        self::assertCount(60, array_unique($seen));
        self::assertCount(10, $this->repository->paginate(SensorListQuery::fromParams(['page' => '3']))['items']);
        self::assertSame([], $this->repository->paginate(SensorListQuery::fromParams(['page' => '4']))['items']);
    }

    public function testSortsByNameDescendingWithIdAsTieBreaker(): void
    {
        $this->db->executeStatement("UPDATE sensors SET name = 'Same name'");

        $items = $this->repository->paginate(SensorListQuery::fromParams(['sort' => 'name', 'order' => 'desc', 'page_size' => '10']))['items'];

        self::assertSame([60, 59, 58, 57, 56, 55, 54, 53, 52, 51], array_column($items, 'id'));
    }

    public function testSortsByCode(): void
    {
        $items = $this->repository->paginate(SensorListQuery::fromParams(['sort' => 'code', 'order' => 'asc', 'page_size' => '3']))['items'];

        self::assertSame(['SN-000001', 'SN-000002', 'SN-000003'], array_column($items, 'code'));
    }

    public function testFiltersByCodePrefixAndCountsFilteredTotal(): void
    {
        $result = $this->repository->paginate(SensorListQuery::fromParams(['code' => 'SN-00001', 'page_size' => '100']));

        // SN-000010 .. SN-000019
        self::assertSame(10, $result['total']);
        self::assertCount(10, $result['items']);
    }

    public function testFiltersByNamePrefixCaseInsensitively(): void
    {
        $result = $this->repository->paginate(SensorListQuery::fromParams(['name' => 'humidity', 'page_size' => '100']));

        self::assertSame(10, $result['total']);
        foreach ($result['items'] as $item) {
            self::assertSame('humidity', $item['type']);
        }
    }

    public function testLikeWildcardsInFilterAreLiteral(): void
    {
        $result = $this->repository->paginate(SensorListQuery::fromParams(['code' => '%%%']));

        self::assertSame(0, $result['total']);
    }

    public function testFiltersByCreatedRange(): void
    {
        $this->db->executeStatement("UPDATE sensors SET created_at = '2026-01-15 12:00:00' WHERE id <= 5");
        $this->db->executeStatement("UPDATE sensors SET created_at = '2026-03-01 00:00:00' WHERE id > 5");

        $result = $this->repository->paginate(SensorListQuery::fromParams(['created_from' => '2026-01-15', 'created_to' => '2026-01-15']));

        self::assertSame(5, $result['total']);
    }

    public function testItemShapeHasNoSecrets(): void
    {
        $item = $this->repository->paginate(SensorListQuery::fromParams(['sort' => 'code', 'order' => 'asc', 'page_size' => '1']))['items'][0];

        self::assertSame(
            ['id', 'code', 'name', 'type', 'unit', 'device_id', 'location', 'status', 'created_at', 'last_seen_at'],
            array_keys($item),
        );
        self::assertIsInt($item['id']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $item['created_at']);
    }

    public function testCountOfItemsMatchesPageSizeForSmallTable(): void
    {
        self::assertSame(60, count($this->repository->paginate(SensorListQuery::fromParams(['page_size' => '100']))['items']));
    }
}
