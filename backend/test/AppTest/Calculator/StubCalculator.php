<?php

declare(strict_types=1);

namespace AppTest\Calculator;

use App\Calculator\CalculatorInterface;
use App\Calculator\CalculatorMode;

final class StubCalculator implements CalculatorInterface
{
    public int $calls = 0;
    /** @var list<array{int, string}> */
    public array $arguments = [];

    public function __construct(
        private readonly string $name,
        private readonly CalculatorMode $mode,
        private readonly float $value,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function mode(): CalculatorMode
    {
        return $this->mode;
    }

    public function calculate(int $sensorId, string $window): float
    {
        ++$this->calls;
        $this->arguments[] = [$sensorId, $window];

        return $this->value;
    }
}
