<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Mezzio\Authentication\OAuth2\Entity\ScopeEntity;

use function str_starts_with;

/**
 * The token's only scope is `role:<role name>`: the role is read from the DB on issuance (and on every refresh),
 * the client cannot request it. This way the role is in the signed access token and needs no DB query.
 */
final class ScopeRepository implements ScopeRepositoryInterface
{
    public const ROLE_PREFIX = 'role:';

    public function __construct(private readonly Connection $db)
    {
    }

    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        if (! str_starts_with($identifier, self::ROLE_PREFIX)) {
            return null;
        }

        $scope = new ScopeEntity();
        $scope->setIdentifier($identifier);

        return $scope;
    }

    /** @inheritDoc */
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null,
    ): array {
        $role = $this->db->fetchOne(
            'SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
            [$userIdentifier],
        );

        $scope = $role === false ? null : $this->getScopeEntityByIdentifier(self::ROLE_PREFIX . $role);

        return $scope === null ? [] : [$scope];
    }
}
