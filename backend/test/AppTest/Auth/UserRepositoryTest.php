<?php

declare(strict_types=1);

namespace AppTest\Auth;

use App\Auth\Repository\ClientRepository;
use App\Auth\Repository\UserRepository;
use AppTest\Db\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Exception\OAuthServerException;
use Mezzio\Authentication\OAuth2\Entity\ClientEntity;

use function password_hash;
use function password_verify;

use const PASSWORD_DEFAULT;

final class UserRepositoryTest extends DatabaseTestCase
{
    private const ADMIN = 'root@example.com';

    private ClientEntity $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateUp();
        $this->client = new ClientEntity(ClientRepository::CLIENT_ID, 'spa', '', false);
        $this->db->executeStatement(
            "INSERT INTO users (email, password_hash, role_id) SELECT ?, '', id FROM roles WHERE name = 'admin'",
            [self::ADMIN],
        );
    }

    public function testFirstLoginSetsThePassword(): void
    {
        $user = $this->repository($this->db)->getUserEntityByUserCredentials(self::ADMIN, 'first-password', 'password', $this->client);

        self::assertNotNull($user);
        self::assertTrue(password_verify('first-password', (string) $this->storedHash()));
    }

    public function testPasswordCannotBeSetTwice(): void
    {
        $repository = $this->repository($this->db);
        $repository->getUserEntityByUserCredentials(self::ADMIN, 'first-password', 'password', $this->client);

        $second = $repository->getUserEntityByUserCredentials(self::ADMIN, 'attacker-password', 'password', $this->client);

        self::assertNull($second);
        self::assertTrue(password_verify('first-password', (string) $this->storedHash()));
    }

    public function testLoserOfTheRaceDoesNotOverwriteThePassword(): void
    {
        // The first SELECT still sees an empty password, but another request has already set it before the UPDATE
        $racing = new class ($this->db) extends Connection {
            public function __construct(private readonly Connection $real)
            {
            }

            public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
            {
                $row = $this->real->fetchAssociative($query, $params, $types);
                $this->real->executeStatement(
                    'UPDATE users SET password_hash = ? WHERE email = ?',
                    [password_hash('winner-password', PASSWORD_DEFAULT), $params[0]],
                );

                return $row;
            }

            public function executeStatement(string $sql, array $params = [], array $types = []): int|string
            {
                return $this->real->executeStatement($sql, $params, $types);
            }
        };

        $loser = $this->repository($racing)->getUserEntityByUserCredentials(self::ADMIN, 'loser-password', 'password', $this->client);

        self::assertNull($loser);
        self::assertTrue(password_verify('winner-password', (string) $this->storedHash()));
    }

    public function testOtherEmailWithEmptyPasswordCannotClaim(): void
    {
        $this->db->executeStatement(
            "INSERT INTO users (email, password_hash, role_id) SELECT 'other@example.com', '', id FROM roles WHERE name = 'user'",
        );

        $user = $this->repository($this->db)->getUserEntityByUserCredentials('other@example.com', 'some-password', 'password', $this->client);

        self::assertNull($user);
    }

    public function testShortFirstPasswordIsRejectedWithoutChangingAnything(): void
    {
        $this->expectException(OAuthServerException::class);

        try {
            $this->repository($this->db)->getUserEntityByUserCredentials(self::ADMIN, 'short', 'password', $this->client);
        } finally {
            self::assertSame('', $this->storedHash());
        }
    }

    public function testBlockedUserCannotSignIn(): void
    {
        $this->db->executeStatement('UPDATE users SET password_hash = ?, is_active = 0', [password_hash('secret-pass-1', PASSWORD_DEFAULT)]);

        $user = $this->repository($this->db)->getUserEntityByUserCredentials(self::ADMIN, 'secret-pass-1', 'password', $this->client);

        self::assertNull($user);
    }

    private function repository(Connection $db): UserRepository
    {
        return new UserRepository($db, self::ADMIN);
    }

    private function storedHash(): string|false
    {
        return $this->db->fetchOne('SELECT password_hash FROM users WHERE email = ?', [self::ADMIN]);
    }
}
