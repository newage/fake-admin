<?php

declare(strict_types=1);

namespace App\Auth\Handler;

use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Psr\Container\ContainerInterface;

final class RevokeTokenHandlerFactory
{
    public function __invoke(ContainerInterface $container): RevokeTokenHandler
    {
        return new RevokeTokenHandler(
            $container->get(RefreshTokenRepositoryInterface::class),
            (string) $container->get('config')['authentication']['encryption_key'],
        );
    }
}
