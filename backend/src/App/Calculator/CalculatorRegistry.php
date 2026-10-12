<?php

declare(strict_types=1);

namespace App\Calculator;

use LogicException;

use function sprintf;

final class CalculatorRegistry
{
    /** @var array<string, CalculatorInterface> */
    private array $calculators = [];

    public function register(CalculatorInterface $calculator): void
    {
        if (isset($this->calculators[$calculator->name()])) {
            throw new LogicException(sprintf('Calculator "%s" is already registered', $calculator->name()));
        }

        $this->calculators[$calculator->name()] = $calculator;
    }

    /** @throws UnknownCalculator */
    public function get(string $name): CalculatorInterface
    {
        return $this->calculators[$name] ?? throw UnknownCalculator::named($name);
    }
}
