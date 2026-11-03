<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Manual payment reminders for overdue sales: a log of what was prepared or sent, the
 * optional collection contact on the customer, and the new receivables.remind permission
 * granted to the administrator role only (least privilege; cashiers do not get it).
 */
final class Version20261102000000_collection_reminders extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sales_collection_reminder, cliente.collection_contact_name and receivables.remind for administrators';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sales_collection_reminder (
                id INT AUTO_INCREMENT NOT NULL,
                sale_record_id VARCHAR(36) NOT NULL,
                customer_id INT NOT NULL,
                channel VARCHAR(16) NOT NULL,
                tone VARCHAR(16) NOT NULL,
                status VARCHAR(16) NOT NULL,
                body LONGTEXT NOT NULL,
                recipient_phone VARCHAR(32) DEFAULT NULL,
                recipient_email VARCHAR(255) DEFAULT NULL,
                failure_reason VARCHAR(255) DEFAULT NULL,
                created_by_user_id INT DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_sales_collection_reminder_sale_channel (sale_record_id, channel, created_at),
                INDEX idx_sales_collection_reminder_customer (customer_id),
                INDEX idx_sales_collection_reminder_created (created_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE cliente ADD collection_contact_name VARCHAR(120) DEFAULT NULL');

        $this->addSql(
            "INSERT IGNORE INTO role_permission (role_id, permission) SELECT 2, 'receivables.remind' WHERE EXISTS (SELECT 1 FROM rol WHERE rol_id = 2)",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM role_permission WHERE permission = 'receivables.remind'");
        $this->addSql('ALTER TABLE cliente DROP collection_contact_name');
        $this->addSql('DROP TABLE sales_collection_reminder');
    }
}
