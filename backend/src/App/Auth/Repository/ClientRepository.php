<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Mezzio\Authentication\OAuth2\Entity\ClientEntity;

use function in_array;

/**
 * The only public client: the admin's own SPA. There is no clients table.
 */
final class ClientRepository implements ClientRepositoryInterface
{
    public const CLIENT_ID = 'fake-admin-spa';

    private const GRANTS = ['password', 'refresh_token'];

    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        if ($clientIdentifier !== self::CLIENT_ID) {
            return null;
        }

        return new ClientEntity(self::CLIENT_ID, 'fake-admin SPA', '', false);
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        return $clientIdentifier === self::CLIENT_ID && in_array($grantType, self::GRANTS, true);
    }
}
