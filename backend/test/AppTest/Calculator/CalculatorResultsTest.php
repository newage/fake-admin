<?php

declare(strict_types=1);

namespace AppTest\Calculator;

use App\Calculator\CalculatedValue;
use App\Calculator\CalculatorInterface;
use App\Calculator\CalculatorMode;
use App\Calculator\CalculatorRegistry;
use App\Calculator\CalculatorResults;
use App\Calculator\UnknownCalculator;
use AppTest\Db\DatabaseTestCase;
use LogicException;

final class CalculatorResultsTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateUp();
        $this->db->executeStatement("INSERT INTO sensors (id, code, name, type, unit) VALUES (7, 'SN-7', 'Sensor 7', 'temperature', '°C')");
    }

    public function testLiveCalculatorIsComputedOnEveryRequest(): void
    {
        $live    = new StubCalculator('avg', CalculatorMode::Live, 21.5);
        $results = $this->results($live);

        $first  = $results->get('avg', 7, '1h');
        $second = $results->get('avg', 7, '1h');

        self::assertInstanceOf(CalculatedValue::class, $first);
        self::assertSame(21.5, $first->value);
        self::assertSame(21.5, $second?->value);
        self::assertSame(2, $live->calls, 'live mode computes at request time, no caching');
        self::assertSame([[7, '1h'], [7, '1h']], $live->arguments);
    }

    public function testCachedCalculatorReadsStoredValueAndNeverComputes(): void
    {
        $cached = new StubCalculator('avg', CalculatorMode::Cached, 0.0);
        $this->db->executeStatement(
            "INSERT INTO calc_results (calculator, sensor_id, time_window, value, computed_at) VALUES ('avg', 7, '1h', 19.25, '2026-10-11 10:00:00')",
        );

        $value = $this->results($cached)->get('avg', 7, '1h');

        self::assertSame(19.25, $value?->value);
        self::assertSame('2026-10-11T10:00:00Z', $value?->computedAt);
        self::assertSame(0, $cached->calls, 'cached mode must not compute at request time');
    }

    public function testCachedValueIsKeyedByCalculatorSensorAndWindow(): void
    {
        $this->db->executeStatement(
            "INSERT INTO calc_results (calculator, sensor_id, time_window, value, computed_at) VALUES ('avg', 7, '1h', 1, '2026-10-11 10:00:00'), ('avg', 7, '24h', 2, '2026-10-11 10:00:00')",
        );
        $results = $this->results(new StubCalculator('avg', CalculatorMode::Cached, 0.0));

        self::assertSame(2.0, $results->get('avg', 7, '24h')?->value);
        self::assertNull($results->get('avg', 7, '7d'));
    }

    public function testCachedValueNotComputedYetIsNullWithoutComputing(): void
    {
        $cached = new StubCalculator('avg', CalculatorMode::Cached, 0.0);

        self::assertNull($this->results($cached)->get('avg', 7, '1h'));
        self::assertSame(0, $cached->calls);
    }

    public function testUnknownCalculatorIsReported(): void
    {
        $this->expectException(UnknownCalculator::class);

        $this->results(new StubCalculator('avg', CalculatorMode::Live, 1.0))->get('median', 7, '1h');
    }

    public function testRegistryRejectsDuplicateNames(): void
    {
        $registry = new CalculatorRegistry();
        $registry->register(new StubCalculator('avg', CalculatorMode::Live, 1.0));

        $this->expectException(LogicException::class);

        $registry->register(new StubCalculator('avg', CalculatorMode::Cached, 2.0));
    }

    private function results(CalculatorInterface ...$calculators): CalculatorResults
    {
        $registry = new CalculatorRegistry();
        foreach ($calculators as $calculator) {
            $registry->register($calculator);
        }

        return new CalculatorResults($registry, $this->db);
    }
}
