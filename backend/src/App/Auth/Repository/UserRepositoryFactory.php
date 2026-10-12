<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;

use function getenv;

final class UserRepositoryFactory
{
    public function __invoke(ContainerInterface $container): UserRepository
    {
        return new UserRepository($container->get(Connection::class), (string) getenv('SUPERADMIN_EMAIL'));
    }
}
