<?php

declare(strict_types=1);

namespace AppTest\Sensor;

use App\Sensor\SensorSeeder;
use AppTest\Db\DatabaseTestCase;

final class SensorSeederTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateUp();
    }

    public function testSecondRunDoesNotDuplicate(): void
    {
        $seeder = new SensorSeeder($this->db);

        $first  = $seeder->seed(1200);
        $second = $seeder->seed(1200);

        self::assertSame(1200, $first);
        self::assertSame(0, $second);
        self::assertSame(1200, (int) $this->db->fetchOne('SELECT COUNT(*) FROM sensors'));
        self::assertSame(1200, (int) $this->db->fetchOne('SELECT COUNT(DISTINCT code) FROM sensors'));
    }

    public function testRaisingCountAddsOnlyMissing(): void
    {
        $seeder = new SensorSeeder($this->db);
        $seeder->seed(10);

        self::assertSame(5, $seeder->seed(15));
        self::assertSame(15, (int) $this->db->fetchOne('SELECT COUNT(*) FROM sensors'));
    }

    public function testNoSecretsAreGenerated(): void
    {
        (new SensorSeeder($this->db))->seed(20);

        self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM sensors WHERE secret_enc IS NOT NULL OR key_version IS NOT NULL'));
    }
}
