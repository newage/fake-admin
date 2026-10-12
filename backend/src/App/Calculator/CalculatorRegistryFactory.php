<?php

declare(strict_types=1);

namespace App\Calculator;

use Psr\Container\ContainerInterface;

/**
 * Registers calculators from the `calculators` config (a list of service names in the container).
 */
final class CalculatorRegistryFactory
{
    public function __invoke(ContainerInterface $container): CalculatorRegistry
    {
        $registry = new CalculatorRegistry();
        foreach ($container->get('config')['calculators'] ?? [] as $service) {
            $registry->register($container->get($service));
        }

        return $registry;
    }
}
