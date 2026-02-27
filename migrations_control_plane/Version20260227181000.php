<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227181000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Control-plane hardening: align key types, enforce foreign keys, and guarantee a single primary domain per tenant.';
    }

    public function up(Schema $schema): void
    {
        // Re-run safety when a previous execution failed mid-migration.
        $this->addSql('ALTER TABLE account_tenants DROP FOREIGN KEY IF EXISTS fk_account_tenants_account');
        $this->addSql('ALTER TABLE account_tenants DROP FOREIGN KEY IF EXISTS fk_account_tenants_tenant');
        $this->addSql('ALTER TABLE tenant_provisions DROP FOREIGN KEY IF EXISTS fk_tenant_provisions_tenant');
        $this->addSql('ALTER TABLE tenant_provisions DROP FOREIGN KEY IF EXISTS fk_tenant_provisions_account');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY IF EXISTS fk_subscriptions_tenant');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY IF EXISTS fk_subscriptions_account');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY IF EXISTS fk_subscriptions_provision');
        $this->addSql('ALTER TABLE payment_events DROP FOREIGN KEY IF EXISTS fk_payment_events_subscription');
        $this->addSql('ALTER TABLE payment_events DROP FOREIGN KEY IF EXISTS fk_payment_events_tenant');
        $this->addSql('ALTER TABLE tenant_backups DROP FOREIGN KEY IF EXISTS fk_tenant_backups_tenant');

        // Align signedness so foreign keys can be created safely.
        // tenants.id is signed INT, so tenant_id references must also be signed INT.
        $this->addSql('ALTER TABLE account_tenants MODIFY account_id INT UNSIGNED NOT NULL, MODIFY tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE tenant_provisions MODIFY tenant_id INT NULL, MODIFY account_id INT UNSIGNED NULL');
        $this->addSql('ALTER TABLE subscriptions MODIFY tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE payment_events MODIFY tenant_id INT NULL');
        $this->addSql('ALTER TABLE tenant_backups MODIFY tenant_id INT NOT NULL');

        // Normalize legacy/orphan rows before adding strict foreign keys.
        $this->addSql('DELETE at FROM account_tenants at LEFT JOIN accounts a ON a.id = at.account_id WHERE a.id IS NULL');
        $this->addSql('DELETE at FROM account_tenants at LEFT JOIN tenants t ON t.id = at.tenant_id WHERE t.id IS NULL');
        $this->addSql('UPDATE tenant_provisions tp LEFT JOIN tenants t ON t.id = tp.tenant_id SET tp.tenant_id = NULL WHERE tp.tenant_id IS NOT NULL AND t.id IS NULL');
        $this->addSql('UPDATE tenant_provisions tp LEFT JOIN accounts a ON a.id = tp.account_id SET tp.account_id = NULL WHERE tp.account_id IS NOT NULL AND a.id IS NULL');
        $this->addSql('UPDATE subscriptions s LEFT JOIN tenant_provisions tp ON tp.id = s.provision_id SET s.provision_id = NULL WHERE s.provision_id IS NOT NULL AND tp.id IS NULL');
        $this->addSql('UPDATE payment_events pe LEFT JOIN subscriptions s ON s.id = pe.subscription_id SET pe.subscription_id = NULL WHERE pe.subscription_id IS NOT NULL AND s.id IS NULL');
        $this->addSql('UPDATE payment_events pe LEFT JOIN tenants t ON t.id = pe.tenant_id SET pe.tenant_id = NULL WHERE pe.tenant_id IS NOT NULL AND t.id IS NULL');

        // Ensure one primary domain per tenant (MySQL-compatible partial uniqueness via generated column).
        $this->addSql('ALTER TABLE tenant_domains ADD COLUMN IF NOT EXISTS primary_tenant_id INT GENERATED ALWAYS AS (CASE WHEN is_primary = 1 THEN tenant_id ELSE NULL END) STORED');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_tenant_domains_one_primary ON tenant_domains (primary_tenant_id)');

        // Add missing referential integrity.
        $this->addSql('ALTER TABLE account_tenants ADD CONSTRAINT fk_account_tenants_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE account_tenants ADD CONSTRAINT fk_account_tenants_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE tenant_provisions ADD CONSTRAINT fk_tenant_provisions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE tenant_provisions ADD CONSTRAINT fk_tenant_provisions_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT fk_subscriptions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT fk_subscriptions_account FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT fk_subscriptions_provision FOREIGN KEY (provision_id) REFERENCES tenant_provisions (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE payment_events ADD CONSTRAINT fk_payment_events_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE payment_events ADD CONSTRAINT fk_payment_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE tenant_backups ADD CONSTRAINT fk_tenant_backups_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_backups DROP FOREIGN KEY IF EXISTS fk_tenant_backups_tenant');

        $this->addSql('ALTER TABLE payment_events DROP FOREIGN KEY IF EXISTS fk_payment_events_tenant');
        $this->addSql('ALTER TABLE payment_events DROP FOREIGN KEY IF EXISTS fk_payment_events_subscription');

        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY IF EXISTS fk_subscriptions_provision');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY IF EXISTS fk_subscriptions_account');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY IF EXISTS fk_subscriptions_tenant');

        $this->addSql('ALTER TABLE tenant_provisions DROP FOREIGN KEY IF EXISTS fk_tenant_provisions_account');
        $this->addSql('ALTER TABLE tenant_provisions DROP FOREIGN KEY IF EXISTS fk_tenant_provisions_tenant');

        $this->addSql('ALTER TABLE account_tenants DROP FOREIGN KEY IF EXISTS fk_account_tenants_tenant');
        $this->addSql('ALTER TABLE account_tenants DROP FOREIGN KEY IF EXISTS fk_account_tenants_account');

        $this->addSql('DROP INDEX IF EXISTS uq_tenant_domains_one_primary ON tenant_domains');
        $this->addSql('ALTER TABLE tenant_domains DROP COLUMN IF EXISTS primary_tenant_id');

        $this->addSql('ALTER TABLE tenant_backups MODIFY tenant_id INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE payment_events MODIFY tenant_id INT UNSIGNED NULL');
        $this->addSql('ALTER TABLE subscriptions MODIFY tenant_id INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE tenant_provisions MODIFY tenant_id INT UNSIGNED NULL, MODIFY account_id INT NULL');
        $this->addSql('ALTER TABLE account_tenants MODIFY account_id INT NOT NULL, MODIFY tenant_id INT NOT NULL');
    }
}
