<?php

declare(strict_types=1);

namespace App\Console;

use App\User\SuperAdminInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function getenv;
use function sprintf;

#[AsCommand(name: 'app:superadmin:ensure', description: 'Створює суперадміна з SUPERADMIN_EMAIL (ідемпотентно)')]
final class EnsureSuperAdminCommand extends Command
{
    public function __construct(private readonly SuperAdminInstaller $installer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) getenv('SUPERADMIN_EMAIL');
        if ($email === '') {
            $output->writeln('<error>SUPERADMIN_EMAIL is not set</error>');

            return Command::FAILURE;
        }

        $output->writeln($this->installer->ensure($email)
            ? sprintf('Created superadmin %s; set the password by signing in.', $email)
            : sprintf('Superadmin %s already exists; nothing changed.', $email));

        return Command::SUCCESS;
    }
}
