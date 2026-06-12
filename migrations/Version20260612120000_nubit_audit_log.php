<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612120000_nubit_audit_log extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audit trail from nubitio/admin-bundle: nubit_audit_log stores field-level diffs of #[Auditable] entities (Supplier, Product).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE nubit_audit_log (
                id INT AUTO_INCREMENT NOT NULL,
                resource VARCHAR(100) NOT NULL,
                resource_id VARCHAR(64) NOT NULL,
                action VARCHAR(10) NOT NULL,
                changes JSON NOT NULL,
                username VARCHAR(180) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_NUBIT_AUDIT_RESOURCE (resource, resource_id, created_at),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE nubit_audit_log');
    }
}
