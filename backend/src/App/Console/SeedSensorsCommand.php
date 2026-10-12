<?php

declare(strict_types=1);

namespace App\Console;

use App\Sensor\SensorSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(name: 'app:sensors:seed', description: 'Заводить фейкові датчики (ідемпотентно за code)')]
final class SeedSensorsCommand extends Command
{
    public function __construct(private readonly SensorSeeder $seeder)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('count', null, InputOption::VALUE_REQUIRED, 'Скільки датчиків має існувати', '5000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = (int) $input->getOption('count');
        if ($count < 1) {
            $output->writeln('<error>--count must be a positive integer</error>');

            return Command::INVALID;
        }

        $output->writeln(sprintf('Created %d sensors (target %d).', $this->seeder->seed($count), $count));

        return Command::SUCCESS;
    }
}
