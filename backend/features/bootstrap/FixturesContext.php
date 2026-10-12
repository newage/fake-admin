<?php

declare(strict_types=1);

use App\Db\ConnectionFactory;
use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Users for scenarios are created directly in the DB used by the running stack;
 * everything with the @behat.test domain is deleted before and after each scenario.
 */
final class FixturesContext implements Context
{
    private Connection $db;
    private bool $createdReadingsTable = false;
    /** @var array{exists: bool, hash: string}|null */
    private ?array $superAdminBackup = null;

    public function __construct()
    {
        $this->db = DriverManager::getConnection(ConnectionFactory::params());
    }

    #[BeforeScenario]
    #[AfterScenario]
    public function cleanUp(): void
    {
        if ($this->db->createSchemaManager()->tablesExist(['sensor_readings'])) {
            $this->db->executeStatement("DELETE FROM sensor_readings WHERE sensor_id IN (SELECT id FROM sensors WHERE code LIKE 'BHT-%')");
        }
        $this->db->executeStatement("DELETE FROM users WHERE email LIKE '%@behat.test'");
        $this->db->executeStatement("DELETE FROM sensors WHERE code LIKE 'BHT-%'");
    }

    #[Given('/^(\d+) test sensors with code prefix "BHT-"$/')]
    public function testSensors(string $count): void
    {
        for ($i = 1; $i <= (int) $count; ++$i) {
            $this->db->executeStatement(
                "INSERT INTO sensors (code, name, type, unit) VALUES (?, ?, 'temperature', '°C')",
                [sprintf('BHT-%04d', $i), sprintf('Behat sensor %d', $i)],
            );
        }
    }

    #[Given('/^sensor "([^"]+)" has (\d+) readings one minute apart starting at "([^"]+)"$/')]
    public function sensorHasReadings(string $code, string $count, string $start): void
    {
        // The readings table is created by consumers; if it does not exist yet, create a minimal one for the duration of the scenario
        if (! $this->db->createSchemaManager()->tablesExist(['sensor_readings'])) {
            $this->db->executeStatement(
                'CREATE TABLE sensor_readings (sensor_id BIGINT UNSIGNED NOT NULL, ts DATETIME NOT NULL, value DOUBLE NOT NULL, seq BIGINT DEFAULT NULL, PRIMARY KEY (sensor_id, ts))',
            );
            $this->createdReadingsTable = true;
        }

        $sensorId = $this->db->fetchOne('SELECT id FROM sensors WHERE code = ?', [$code]);
        for ($i = 0; $i < (int) $count; ++$i) {
            $this->db->executeStatement(
                'INSERT INTO sensor_readings (sensor_id, ts, value) VALUES (?, DATE_ADD(?, INTERVAL ? MINUTE), ?)',
                [$sensorId, $start, $i, 20 + $i],
            );
        }
    }

    #[AfterScenario]
    public function dropCreatedReadingsTable(): void
    {
        if ($this->createdReadingsTable) {
            $this->db->executeStatement('DROP TABLE sensor_readings');
            $this->createdReadingsTable = false;
        }
    }

    #[Given('the superadmin has not set a password yet')]
    public function theSuperadminHasNotSetAPassword(): void
    {
        $email = $this->superAdminEmail();
        $hash  = $this->db->fetchOne('SELECT password_hash FROM users WHERE email = ?', [$email]);

        $this->superAdminBackup ??= ['exists' => $hash !== false, 'hash' => (string) $hash];

        if ($hash === false) {
            $this->db->executeStatement(
                "INSERT INTO users (email, password_hash, role_id) SELECT ?, '', id FROM roles WHERE name = 'admin'",
                [$email],
            );
        } else {
            $this->db->executeStatement("UPDATE users SET password_hash = '' WHERE email = ?", [$email]);
        }
    }

    /** Restores the superadmin to how it was before the scenario (the dev DB may have a real password). */
    #[AfterScenario('@superadmin')]
    public function restoreSuperAdmin(): void
    {
        if ($this->superAdminBackup === null) {
            return;
        }

        $email = $this->superAdminEmail();
        if ($this->superAdminBackup['exists']) {
            $this->db->executeStatement('UPDATE users SET password_hash = ? WHERE email = ?', [$this->superAdminBackup['hash'], $email]);
        } else {
            $this->db->executeStatement('DELETE FROM users WHERE email = ?', [$email]);
        }
        $this->db->executeStatement('DELETE FROM refresh_tokens WHERE user_id NOT IN (SELECT id FROM users)');
        $this->superAdminBackup = null;
    }

    public function superAdminEmail(): string
    {
        return (string) (getenv('SUPERADMIN_EMAIL') ?: throw new RuntimeException('SUPERADMIN_EMAIL is not set'));
    }

    #[Given('an active :role user :email with password :password')]
    public function anActiveUser(string $role, string $email, string $password): void
    {
        $this->createUser($role, $email, $password, 1);
    }

    #[Given('a blocked :role user :email with password :password')]
    public function aBlockedUser(string $role, string $email, string $password): void
    {
        $this->createUser($role, $email, $password, 0);
    }

    #[Given('the user :email is blocked')]
    public function theUserIsBlocked(string $email): void
    {
        $this->db->executeStatement('UPDATE users SET is_active = 0 WHERE email = ?', [$email]);
    }

    private function createUser(string $role, string $email, string $password, int $isActive): void
    {
        $this->db->executeStatement(
            'INSERT INTO users (email, password_hash, role_id, is_active) SELECT ?, ?, id, ? FROM roles WHERE name = ?',
            [$email, password_hash($password, PASSWORD_DEFAULT), $isActive, $role],
        );
    }
}
