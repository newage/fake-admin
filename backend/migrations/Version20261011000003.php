<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Initial roles and permission matrix: admin and manager see sensors and their readings, user sees nothing. */
final class Version20261011000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed roles and initial role_permissions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO roles (name) VALUES ('admin'), ('manager'), ('user')");
        $this->addSql(<<<'SQL'
            INSERT INTO role_permissions (role_id, permission)
            SELECT r.id, p.permission
              FROM roles r
              JOIN (SELECT 'sensors.list' AS permission UNION ALL SELECT 'sensors.readings') p
             WHERE r.name IN ('admin', 'manager')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM role_permissions');
        $this->addSql('DELETE FROM roles');
    }
}
