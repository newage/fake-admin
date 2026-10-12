<?php

declare(strict_types=1);

namespace App\User;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use InvalidArgumentException;

use function filter_var;
use function implode;
use function in_array;
use function mb_strlen;
use function password_hash;
use function sprintf;

use const FILTER_VALIDATE_EMAIL;
use const PASSWORD_DEFAULT;

/**
 * Creates a `manager` or `user`. There is no user management screen in this iteration;
 * the superadmin is created separately (SuperAdminInstaller).
 */
final class UserCreator
{
    private const ALLOWED_ROLES = ['manager', 'user'];
    private const MIN_PASSWORD  = 8;

    public function __construct(private readonly Connection $db)
    {
    }

    public function create(string $email, string $password, string $role): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid email', $email));
        }
        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            throw new InvalidArgumentException(sprintf('Role must be one of: %s', implode(', ', self::ALLOWED_ROLES)));
        }
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            throw new InvalidArgumentException(sprintf('Password must be at least %d characters', self::MIN_PASSWORD));
        }

        try {
            $this->db->executeStatement(
                'INSERT INTO users (email, password_hash, role_id) SELECT ?, ?, id FROM roles WHERE name = ?',
                [$email, password_hash($password, PASSWORD_DEFAULT), $role],
            );
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException(sprintf('User %s already exists', $email));
        }
    }
}
