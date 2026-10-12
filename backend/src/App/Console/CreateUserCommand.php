<?php

declare(strict_types=1);

namespace App\Console;

use App\User\UserCreator;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

use function sprintf;

#[AsCommand(name: 'app:user:create', description: 'Створює користувача з роллю manager або user')]
final class CreateUserCommand extends Command
{
    public function __construct(private readonly UserCreator $creator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'manager або user', 'user')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Якщо не задано, запитується інтерактивно');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $password = $input->getOption('password');
        if ($password === null) {
            $question = (new Question('Password: '))->setHidden(true);
            $password = (string) $this->getHelper('question')->ask($input, $output, $question);
        }

        try {
            $this->creator->create((string) $input->getArgument('email'), (string) $password, (string) $input->getOption('role'));
        } catch (InvalidArgumentException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln('User created.');

        return Command::SUCCESS;
    }
}
