<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612000000_nubit_media extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adopt the nubitio/admin-bundle media library: rename media to nubit_media and add the upload metadata columns. InnoDB keeps the product.imagen / company.logo foreign keys pointing at the renamed table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE media TO nubit_media');

        $this->addSql(<<<'SQL'
            ALTER TABLE nubit_media
                ADD original_name VARCHAR(255) DEFAULT NULL,
                ADD mime_type VARCHAR(100) DEFAULT NULL,
                ADD size INT DEFAULT NULL,
                ADD created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);

        // Legacy rows predate the metadata columns — backfill a creation date
        // so the column can become NOT NULL as the bundle entity declares it.
        $this->addSql('UPDATE nubit_media SET created_at = NOW() WHERE created_at IS NULL');
        $this->addSql("ALTER TABLE nubit_media MODIFY created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE nubit_media DROP COLUMN original_name, DROP COLUMN mime_type, DROP COLUMN size, DROP COLUMN created_at');
        $this->addSql('RENAME TABLE nubit_media TO media');
    }
}
