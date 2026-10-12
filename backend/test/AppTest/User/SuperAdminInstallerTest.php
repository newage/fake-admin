<?php

declare(strict_types=1);

namespace AppTest\User;

use App\User\SuperAdminInstaller;
use AppTest\Db\DatabaseTestCase;

final class SuperAdminInstallerTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateUp();
    }

    public function testCreatesAdminWithEmptyPassword(): void
    {
        $created = (new SuperAdminInstaller($this->db))->ensure('root@example.com');

        self::assertTrue($created);
        $row = $this->db->fetchAssociative(
            'SELECT u.password_hash, u.is_active, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?',
            ['root@example.com'],
        );
        self::assertSame(['password_hash' => '', 'is_active' => 1, 'role' => 'admin'], $row);
    }

    public function testSecondRunDoesNotDuplicateOrResetPassword(): void
    {
        $installer = new SuperAdminInstaller($this->db);
        $installer->ensure('root@example.com');
        $this->db->executeStatement("UPDATE users SET password_hash = 'hash' WHERE email = ?", ['root@example.com']);

        $created = $installer->ensure('root@example.com');

        self::assertFalse($created);
        self::assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM users'));
        self::assertSame('hash', $this->db->fetchOne('SELECT password_hash FROM users'));
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SuperAdminInstaller($this->db))->ensure('not-an-email');
    }
}
