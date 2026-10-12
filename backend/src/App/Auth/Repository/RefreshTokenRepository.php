<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Mezzio\Authentication\OAuth2\Entity\RefreshTokenEntity;

use function hash;

/**
 * Refresh tokens are stored only as a SHA-256 of the identifier.
 */
final class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $accessToken = $refreshTokenEntity->getAccessToken();

        try {
            $this->db->executeStatement(
                'INSERT INTO refresh_tokens (user_id, token_hash, access_token_id, expires_at) VALUES (?, ?, ?, ?)',
                [
                    $accessToken->getUserIdentifier(),
                    self::hash($refreshTokenEntity->getIdentifier()),
                    $accessToken->getIdentifier(),
                    $refreshTokenEntity->getExpiryDateTime()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $this->db->executeStatement(
            'UPDATE refresh_tokens SET revoked_at = UTC_TIMESTAMP() WHERE token_hash = ? AND revoked_at IS NULL',
            [self::hash($tokenId)],
        );
    }

    /**
     * league calls this method only on a refresh grant and only before issuing new tokens, so here
     * the token is "taken" by an atomic UPDATE: a second parallel request with the same refresh gets
     * "revoked". A token is also considered invalid if it is expired, unknown, or belongs to a blocked user.
     */
    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $claimed = $this->db->executeStatement(
            'UPDATE refresh_tokens rt
               JOIN users u ON u.id = rt.user_id
                SET rt.revoked_at = UTC_TIMESTAMP()
              WHERE rt.token_hash = ?
                AND rt.revoked_at IS NULL
                AND rt.expires_at > UTC_TIMESTAMP()
                AND u.is_active = 1',
            [self::hash($tokenId)],
        );

        return $claimed !== 1;
    }

    private static function hash(string $tokenId): string
    {
        return hash('sha256', $tokenId);
    }
}
