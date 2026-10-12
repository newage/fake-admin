<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Mezzio\Authentication\OAuth2\Entity\UserEntity;

use function mb_strlen;
use function mb_strtolower;
use function password_hash;
use function password_verify;

use const PASSWORD_DEFAULT;

final class UserRepository implements UserRepositoryInterface
{
    public const MIN_PASSWORD_LENGTH = 8;

    private static ?string $dummyHash = null;

    public function __construct(
        private readonly Connection $db,
        private readonly string $superAdminEmail,
    ) {
    }

    public function getUserEntityByUserCredentials(
        string $username,
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity,
    ): ?UserEntityInterface {
        $user = $this->db->fetchAssociative(
            'SELECT id, email, password_hash, is_active FROM users WHERE email = ?',
            [$username],
        );

        if ($user === false || (int) $user['is_active'] !== 1) {
            // Same response time for an unknown and a blocked user
            password_verify($password, self::dummyHash());

            return null;
        }

        if ($user['password_hash'] === '') {
            return $this->claimSuperAdmin((int) $user['id'], (string) $user['email'], $password);
        }

        return password_verify($password, $user['password_hash']) ? new UserEntity((string) $user['id']) : null;
    }

    /**
     * Superadmin's first login: the password is set by a single conditional UPDATE, so of two
     * simultaneous logins only one sets the password.
     */
    private function claimSuperAdmin(int $id, string $email, string $password): ?UserEntityInterface
    {
        if ($this->superAdminEmail === '' || mb_strtolower($email) !== mb_strtolower($this->superAdminEmail)) {
            return null;
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw OAuthServerException::invalidRequest('password', 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters');
        }

        $updated = $this->db->executeStatement(
            "UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ''",
            [password_hash($password, PASSWORD_DEFAULT), $id],
        );

        return $updated === 1 ? new UserEntity((string) $id) : null;
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= password_hash('dummy-password', PASSWORD_DEFAULT);
    }
}
