<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * User profile data for the "Usuarios y permisos" screen: an optional contact e-mail
 * (unique when present) and the timestamp of the last successful sign-in.
 */
final class Version20261107000000_user_email_last_login extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add usuario.email (unique, nullable) and usuario.last_login_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario ADD email VARCHAR(180) DEFAULT NULL, ADD last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_usuario_email ON usuario (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_usuario_email ON usuario');
        $this->addSql('ALTER TABLE usuario DROP email, DROP last_login_at');
    }
}
