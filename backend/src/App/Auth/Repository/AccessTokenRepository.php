<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Mezzio\Authentication\OAuth2\Entity\AccessTokenEntity;

/**
 * An access token lives ≤5 min and is not revoked: the registry only records issuance.
 * Deleting expired rows is a separate maintenance task.
 */
final class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function __construct(private readonly Connection $db)
    {
    }

    /** @inheritDoc */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, ?string $userIdentifier = null): AccessTokenEntityInterface
    {
        $token = new AccessTokenEntity();
        $token->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $token->addScope($scope);
        }
        if ($userIdentifier !== null && $userIdentifier !== '') {
            $token->setUserIdentifier($userIdentifier);
        }

        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        try {
            $this->db->executeStatement(
                'INSERT INTO access_tokens (id, user_id, expires_at) VALUES (?, ?, ?)',
                [
                    $accessTokenEntity->getIdentifier(),
                    $accessTokenEntity->getUserIdentifier(),
                    $accessTokenEntity->getExpiryDateTime()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }
    }

    public function revokeAccessToken(string $tokenId): void
    {
        $this->db->executeStatement('DELETE FROM access_tokens WHERE id = ?', [$tokenId]);
    }

    /** There is no registry of revoked access tokens: the short lifetime replaces revocation. */
    public function isAccessTokenRevoked(string $tokenId): bool
    {
        return false;
    }
}
