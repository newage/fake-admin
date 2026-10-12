<?php

declare(strict_types=1);

namespace AppTest\Db;

use App\Db\ConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Version;
use PHPUnit\Framework\TestCase;

use function dirname;
use function getenv;

/**
 * Base class for tests that hit the test MySQL (fake_admin_test).
 * Each test starts with an empty DB and decides for itself which schema to migrate.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected Connection $db;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(
            ConnectionFactory::params(getenv('DB_NAME_TEST') ?: 'fake_admin_test'),
        );
        $this->dropAllTables();
    }

    protected function tearDown(): void
    {
        $this->dropAllTables();
        $this->db->close();
    }

    protected function migrateUp(): void
    {
        $this->migrate(null);
    }

    protected function migrateDown(): void
    {
        $this->migrate(new Version('0'));
    }

    /** @return list<string> */
    protected function tableNames(): array
    {
        return $this->db->createSchemaManager()->listTableNames();
    }

    private function migrate(?Version $target): void
    {
        // A new DependencyFactory per call: migration instances are "frozen" after execution
        $migrations = DependencyFactory::fromConnection(
            new ConfigurationArray(require dirname(__DIR__, 3) . '/migrations.php'),
            new ExistingConnection($this->db),
        );
        $migrations->getMetadataStorage()->ensureInitialized();
        $calculator = $migrations->getMigrationPlanCalculator();
        $plan       = $calculator->getPlanUntilVersion(
            $target ?? $migrations->getVersionAliasResolver()->resolveVersionAlias('latest'),
        );

        $migrations->getMigrator()->migrate($plan, new MigratorConfiguration());
    }

    private function dropAllTables(): void
    {
        $this->db->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->tableNames() as $table) {
            $this->db->executeStatement('DROP TABLE `' . $table . '`');
        }
        $this->db->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
