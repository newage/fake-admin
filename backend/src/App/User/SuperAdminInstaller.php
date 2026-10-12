<?php

declare(strict_types=1);

namespace App\User;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;

use function filter_var;
use function sprintf;

use const FILTER_VALIDATE_EMAIL;

/**
 * Creates the superadmin with an empty password; the password is set on first login.
 */
final class SuperAdminInstaller
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** @return bool true if the user was created; false if it already exists (nothing changes) */
    public function ensure(string $email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid email', $email));
        }

        $inserted = $this->db->executeStatement(
            "INSERT IGNORE INTO users (email, password_hash, role_id)
             SELECT ?, '', id FROM roles WHERE name = 'admin'",
            [$email],
        );

        return $inserted === 1;
    }
}
