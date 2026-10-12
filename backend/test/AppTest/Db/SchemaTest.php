<?php

declare(strict_types=1);

namespace AppTest\Db;

final class SchemaTest extends DatabaseTestCase
{
    private const TABLES = [
        'users',
        'roles',
        'role_permissions',
        'refresh_tokens',
        'access_tokens',
        'sensors',
        'calc_results',
    ];

    public function testMigrationsCreateAllTables(): void
    {
        $this->migrateUp();

        foreach (self::TABLES as $table) {
            self::assertContains($table, $this->tableNames(), "table $table is missing");
        }
    }

    public function testSensorsHaveIndexesRequiredByAdr(): void
    {
        $this->migrateUp();

        foreach (['name', 'code', 'created_at'] as $column) {
            self::assertTrue(
                $this->hasIndexStartingWith('sensors', $column),
                "sensors.$column must be the first column of an index",
            );
        }
        self::assertTrue($this->hasUniqueIndexOn('sensors', 'code'), 'sensors.code must be unique');
        self::assertTrue($this->hasUniqueIndexOn('users', 'email'), 'users.email must be unique');
    }

    public function testDownRemovesAllTables(): void
    {
        $this->migrateUp();
        $this->migrateDown();

        foreach (self::TABLES as $table) {
            self::assertNotContains($table, $this->tableNames(), "table $table must be removed by down");
        }
    }

    private function hasIndexStartingWith(string $table, string $column): bool
    {
        foreach ($this->db->createSchemaManager()->listTableIndexes($table) as $index) {
            if ($index->getColumns()[0] === $column) {
                return true;
            }
        }

        return false;
    }

    private function hasUniqueIndexOn(string $table, string $column): bool
    {
        foreach ($this->db->createSchemaManager()->listTableIndexes($table) as $index) {
            if ($index->isUnique() && $index->getColumns() === [$column]) {
                return true;
            }
        }

        return false;
    }
}
