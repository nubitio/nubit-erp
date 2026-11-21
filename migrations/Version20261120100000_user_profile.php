<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Self-service profile data on the user: display name, phone, photo and per-account preferences. */
final class Version20261120100000_user_profile extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add display name, phone, avatar and preferences to usuario';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario
            ADD display_name VARCHAR(120) DEFAULT NULL,
            ADD phone VARCHAR(30) DEFAULT NULL,
            ADD avatar_path VARCHAR(255) DEFAULT NULL,
            ADD avatar_updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ADD preferences JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario
            DROP display_name,
            DROP phone,
            DROP avatar_path,
            DROP avatar_updated_at,
            DROP preferences');
    }
}
