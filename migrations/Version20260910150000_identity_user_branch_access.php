<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910150000_identity_user_branch_access extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist the branches a tenant user may select for JWT issuance.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_user_branch_access (
                user_id INT NOT NULL,
                branch_id VARCHAR(255) NOT NULL,
                PRIMARY KEY (user_id, branch_id),
                INDEX IDX_IDENTITY_USER_BRANCH_USER (user_id),
                INDEX IDX_IDENTITY_USER_BRANCH_BRANCH (branch_id),
                CONSTRAINT FK_IDENTITY_USER_BRANCH_USER FOREIGN KEY (user_id) REFERENCES usuario (usuario_id) ON DELETE CASCADE,
                CONSTRAINT FK_IDENTITY_USER_BRANCH_BRANCH FOREIGN KEY (branch_id) REFERENCES local (local_id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity_user_branch_access');
    }
}
