<?php

declare(strict_types=1);

namespace App\Auth\Handler;

use App\Auth\Repository\ClientRepository;
use Laminas\Diactoros\Response\EmptyResponse;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

use function is_array;
use function is_string;
use function json_decode;

/**
 * Refresh token revocation (RFC 7009), used as logout.
 * The token is itself the credential, so Authorization is not needed. An unknown or malformed token
 * is not an error: the response is always 200, so as not to reveal whether the token exists.
 */
final class RevokeTokenHandler implements RequestHandlerInterface
{
    use CryptTrait;

    public function __construct(
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        string $encryptionKey,
    ) {
        $this->setEncryptionKey($encryptionKey);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $token = ((array) $request->getParsedBody())['token'] ?? null;

        if (is_string($token) && $token !== '') {
            $this->revoke($token);
        }

        return new EmptyResponse(200);
    }

    private function revoke(string $token): void
    {
        try {
            $payload = json_decode($this->decrypt($token), true);
        } catch (Throwable) {
            return;
        }

        if (
            is_array($payload)
            && ($payload['client_id'] ?? null) === ClientRepository::CLIENT_ID
            && is_string($payload['refresh_token_id'] ?? null)
        ) {
            $this->refreshTokens->revokeRefreshToken($payload['refresh_token_id']);
        }
    }
}
