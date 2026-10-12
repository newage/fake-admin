<?php

declare(strict_types=1);

namespace AppTest\User;

use App\User\UserCreator;
use AppTest\Db\DatabaseTestCase;
use InvalidArgumentException;

use function password_verify;

final class UserCreatorTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateUp();
    }

    public function testCreatesManagerWithHashedPassword(): void
    {
        (new UserCreator($this->db))->create('m@example.com', 'correct horse', 'manager');

        $row = $this->db->fetchAssociative(
            'SELECT u.password_hash, u.is_active, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?',
            ['m@example.com'],
        );
        self::assertSame('manager', $row['role']);
        self::assertSame(1, $row['is_active']);
        self::assertNotSame('correct horse', $row['password_hash']);
        self::assertTrue(password_verify('correct horse', $row['password_hash']));
    }

    public function testRejectsAdminRole(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new UserCreator($this->db))->create('a@example.com', 'correct horse', 'admin');
    }

    public function testRejectsShortPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new UserCreator($this->db))->create('u@example.com', 'short', 'user');
    }

    public function testRejectsDuplicateEmail(): void
    {
        $creator = new UserCreator($this->db);
        $creator->create('u@example.com', 'correct horse', 'user');

        $this->expectException(InvalidArgumentException::class);

        $creator->create('U@example.com', 'another pass 1', 'manager');
    }
}
