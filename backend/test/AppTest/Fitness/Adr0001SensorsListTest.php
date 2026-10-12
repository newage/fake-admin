<?php

declare(strict_types=1);

namespace AppTest\Fitness;

use App\Sensor\Handler\ListSensorsHandler;
use App\Sensor\SensorRepository;
use App\Sensor\SensorSeeder;
use AppTest\Db\DatabaseTestCase;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;

use function count;
use function json_decode;

/**
 * Fitness functions from docs/adr/0001-server-side-offset-pagination-for-sensors-list.md.
 * Test names match the assert identifiers in the Architecture Contract section.
 */
final class Adr0001SensorsListTest extends DatabaseTestCase
{
    private ListSensorsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateUp();
        (new SensorSeeder($this->db))->seed(150);
        $this->handler = new ListSensorsHandler(new SensorRepository($this->db));
    }

    /** sensors-list-paginated: the list is always paginated and never returns everything */
    public function testSensorsListPaginated(): void
    {
        foreach ([[], ['page_size' => '100'], ['page' => '2'], ['sort' => 'name'], ['name' => 'Temperature']] as $params) {
            $body = $this->get($params);

            self::assertLessThanOrEqual(100, count($body['items']), 'page size is bounded');
            self::assertLessThan(150, count($body['items']), 'the whole table is never returned at once');
            self::assertArrayHasKey('total', $body);
        }

        self::assertCount(25, $this->get([])['items']);
        self::assertSame(422, $this->response(['page_size' => '101'])->getStatusCode());
        self::assertSame(422, $this->response(['page_size' => '1000000'])->getStatusCode());
    }

    /** sensors-text-filter-min-3: a prefix shorter than 3 characters is rejected */
    public function testSensorsTextFilterMin3(): void
    {
        foreach (['name', 'code'] as $field) {
            self::assertSame(422, $this->response([$field => 'ab'])->getStatusCode(), "$field with 2 chars");
            self::assertSame(200, $this->response([$field => 'abc'])->getStatusCode(), "$field with 3 chars");
        }
    }

    /** sensors-list-indexed: name, code, created_at have indexes that the index starts with */
    public function testSensorsListIndexed(): void
    {
        $leading = [];
        foreach ($this->db->createSchemaManager()->listTableIndexes('sensors') as $index) {
            $leading[] = $index->getColumns()[0];
        }

        foreach (['name', 'code', 'created_at'] as $column) {
            self::assertContains($column, $leading, "sensors.$column needs an index");
        }
    }

    /** @param array<string, string> $params @return array<string, mixed> */
    private function get(array $params): array
    {
        return json_decode((string) $this->response($params)->getBody(), true);
    }

    /** @param array<string, string> $params */
    private function response(array $params): ResponseInterface
    {
        return $this->handler->handle((new ServerRequest([], [], '/api/sensors', 'GET'))->withQueryParams($params));
    }
}
