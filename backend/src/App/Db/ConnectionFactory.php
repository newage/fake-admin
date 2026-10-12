<?php

declare(strict_types=1);

namespace App\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Container\ContainerInterface;

use function getenv;

final class ConnectionFactory
{
    /**
     * @return array{driver: string, host: string, port: int, dbname: string, user: string, password: string, charset: string}
     */
    public static function params(?string $database = null): array
    {
        return [
            'driver'   => 'pdo_mysql',
            'host'     => getenv('DB_HOST') ?: 'mysql',
            'port'     => (int) (getenv('DB_PORT') ?: 3306),
            'dbname'   => $database ?? (getenv('DB_NAME') ?: 'fake_admin'),
            'user'     => getenv('DB_USER') ?: 'fake_admin',
            'password' => getenv('DB_PASSWORD') ?: 'fake_admin',
            'charset'  => 'utf8mb4',
        ];
    }

    public function __invoke(ContainerInterface $container): Connection
    {
        return DriverManager::getConnection(self::params());
    }
}
