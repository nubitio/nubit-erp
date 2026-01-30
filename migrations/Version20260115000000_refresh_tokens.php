<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260115000000_refresh_tokens extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refresh_token table to store hashed refresh tokens for revocation/rotation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE refresh_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, jti VARCHAR(64) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', revoked_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9BACE7E7A76ED395 (user_id), UNIQUE INDEX UNIQ_REFRESH_TOKEN_JTI (jti), UNIQUE INDEX UNIQ_REFRESH_TOKEN_TOKEN_HASH (token_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_9BACE7E7A76ED395 FOREIGN KEY (user_id) REFERENCES usuario (usuario_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_9BACE7E7A76ED395');
        $this->addSql('DROP TABLE refresh_token');
    }
}
