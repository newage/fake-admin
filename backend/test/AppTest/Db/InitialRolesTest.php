<?php

declare(strict_types=1);

namespace AppTest\Db;

final class InitialRolesTest extends DatabaseTestCase
{
    public function testRolesExist(): void
    {
        $this->migrateUp();

        $roles = $this->db->fetchFirstColumn('SELECT name FROM roles ORDER BY name');

        self::assertSame(['admin', 'manager', 'user'], $roles);
    }

    public function testPermissionMatrix(): void
    {
        $this->migrateUp();

        $rows = $this->db->fetchAllAssociative(
            'SELECT r.name AS role, p.permission, p.`constraints` AS constraints
               FROM role_permissions p JOIN roles r ON r.id = p.role_id
              ORDER BY r.name, p.permission',
        );

        self::assertSame(
            [
                ['role' => 'admin', 'permission' => 'sensors.list', 'constraints' => null],
                ['role' => 'admin', 'permission' => 'sensors.readings', 'constraints' => null],
                ['role' => 'manager', 'permission' => 'sensors.list', 'constraints' => null],
                ['role' => 'manager', 'permission' => 'sensors.readings', 'constraints' => null],
            ],
            $rows,
        );
    }

    public function testDownRemovesRolesData(): void
    {
        $this->migrateUp();
        $this->migrateDown();

        self::assertNotContains('roles', $this->tableNames());
    }
}
