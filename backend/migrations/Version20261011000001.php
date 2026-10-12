<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Roles, users, role permissions and OAuth tokens. */
final class Version20261011000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Auth tables: roles, users, role_permissions, refresh_tokens, access_tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE roles (
                id   SMALLINT UNSIGNED AUTO_INCREMENT NOT NULL,
                name VARCHAR(32) NOT NULL,
                UNIQUE INDEX uniq_roles_name (name),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id            BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                email         VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL DEFAULT '',
                role_id       SMALLINT UNSIGNED NOT NULL,
                is_active     TINYINT(1) NOT NULL DEFAULT 1,
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE INDEX uniq_users_email (email),
                INDEX idx_users_role_id (role_id),
                PRIMARY KEY (id),
                CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE role_permissions (
                role_id     SMALLINT UNSIGNED NOT NULL,
                permission  VARCHAR(64) NOT NULL,
                `constraints` JSON DEFAULT NULL,
                PRIMARY KEY (role_id, permission),
                CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE access_tokens (
                id         CHAR(80) NOT NULL,
                user_id    BIGINT UNSIGNED NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_access_tokens_user_id (user_id),
                PRIMARY KEY (id),
                CONSTRAINT fk_access_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_tokens (
                id              BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                user_id         BIGINT UNSIGNED NOT NULL,
                token_hash      CHAR(64) NOT NULL,
                access_token_id CHAR(80) NOT NULL,
                expires_at      DATETIME NOT NULL,
                revoked_at      DATETIME DEFAULT NULL,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE INDEX uniq_refresh_tokens_hash (token_hash),
                INDEX idx_refresh_tokens_user_id (user_id),
                PRIMARY KEY (id),
                CONSTRAINT fk_refresh_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE access_tokens');
        $this->addSql('DROP TABLE role_permissions');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE roles');
    }
}
