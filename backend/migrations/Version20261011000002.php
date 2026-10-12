<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Sensors and ready calculator results. The readings table is created by consumers. */
final class Version20261011000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sensor tables: sensors, calc_results';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sensors (
                id           BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                code         VARCHAR(64) NOT NULL,
                name         VARCHAR(255) NOT NULL,
                type         VARCHAR(32) NOT NULL,
                unit         VARCHAR(16) NOT NULL,
                device_id    VARCHAR(64) DEFAULT NULL,
                location     VARCHAR(255) DEFAULT NULL,
                status       VARCHAR(16) NOT NULL DEFAULT 'active',
                secret_enc   VARBINARY(255) DEFAULT NULL,
                key_version  SMALLINT UNSIGNED DEFAULT NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME DEFAULT NULL,
                UNIQUE INDEX uniq_sensors_code (code),
                INDEX idx_sensors_name (name),
                INDEX idx_sensors_created_at (created_at),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE calc_results (
                calculator  VARCHAR(64) NOT NULL,
                sensor_id   BIGINT UNSIGNED NOT NULL,
                time_window VARCHAR(32) NOT NULL,
                value       DOUBLE PRECISION NOT NULL,
                computed_at DATETIME NOT NULL,
                PRIMARY KEY (calculator, sensor_id, time_window),
                CONSTRAINT fk_calc_results_sensor FOREIGN KEY (sensor_id) REFERENCES sensors (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE calc_results');
        $this->addSql('DROP TABLE sensors');
    }
}
