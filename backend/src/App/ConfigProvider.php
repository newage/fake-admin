<?php

declare(strict_types=1);

namespace App;

use Doctrine\DBAL\Connection;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Psr\Log\LoggerInterface;

/**
 * The configuration provider for the App module
 *
 * @see https://docs.laminas.dev/laminas-component-installer/
 */
class ConfigProvider
{
    /**
     * Returns the configuration array
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            // Registered calculators: names of services implementing CalculatorInterface
            'calculators'  => [],
            'console'      => [
                'commands' => [
                    Console\CreateUserCommand::class,
                    Console\EnsureSuperAdminCommand::class,
                    Console\SeedSensorsCommand::class,
                ],
            ],
        ];
    }

    /**
     * Returns the container dependencies
     */
    public function getDependencies(): array
    {
        return [
            'invokables' => [
                Handler\PingHandler::class => Handler\PingHandler::class,
            ],
            'factories'  => [
                Reading\Handler\ListReadingsHandler::class   => ReflectionBasedAbstractFactory::class,
                Reading\ReadingsRepository::class            => ReflectionBasedAbstractFactory::class,
                Calculator\CalculatorRegistry::class         => Calculator\CalculatorRegistryFactory::class,
                Calculator\CalculatorResults::class          => ReflectionBasedAbstractFactory::class,
                Sensor\Handler\ListSensorsHandler::class     => ReflectionBasedAbstractFactory::class,
                Sensor\SensorRepository::class               => ReflectionBasedAbstractFactory::class,
                Rbac\RbacMiddleware::class                    => ReflectionBasedAbstractFactory::class,
                Auth\Middleware\ApiAuthenticationMiddleware::class => ReflectionBasedAbstractFactory::class,
                Auth\Handler\RevokeTokenHandler::class        => Auth\Handler\RevokeTokenHandlerFactory::class,
                Auth\Repository\AccessTokenRepository::class  => ReflectionBasedAbstractFactory::class,
                Auth\Repository\ClientRepository::class       => InvokableFactory::class,
                Auth\Repository\RefreshTokenRepository::class => ReflectionBasedAbstractFactory::class,
                Auth\Repository\ScopeRepository::class        => ReflectionBasedAbstractFactory::class,
                Auth\Repository\UserRepository::class         => Auth\Repository\UserRepositoryFactory::class,
                Console\CreateUserCommand::class       => ReflectionBasedAbstractFactory::class,
                Console\EnsureSuperAdminCommand::class => ReflectionBasedAbstractFactory::class,
                Console\SeedSensorsCommand::class      => ReflectionBasedAbstractFactory::class,
                Sensor\SensorSeeder::class             => ReflectionBasedAbstractFactory::class,
                User\SuperAdminInstaller::class        => ReflectionBasedAbstractFactory::class,
                User\UserCreator::class                => ReflectionBasedAbstractFactory::class,
                Connection::class      => Db\ConnectionFactory::class,
                LoggerInterface::class => Logger\LoggerFactory::class,
            ],
        ];
    }
}
