<?php

declare(strict_types=1);

namespace App\Logger;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use function getenv;

final class LoggerFactory
{
    public function __invoke(ContainerInterface $container): LoggerInterface
    {
        $handler = new StreamHandler('php://stdout', Level::fromName(getenv('LOG_LEVEL') ?: 'Info'));
        $handler->setFormatter(new JsonFormatter());

        return new Logger('app', [$handler]);
    }
}
