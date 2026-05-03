<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Widens domicilio_fiscal from VARCHAR(255) to TEXT in empresa, proveedor,
 * and cliente tables.
 *
 * SUNAT scraping returns fiscal addresses that can exceed 255 characters when
 * they contain multiple address lines with internal whitespace. Using TEXT
 * eliminates the "Data too long for column" error while also matching the
 * textarea UI control now used for this field.
 */
final class Version20260504000002_fiscal_address_text extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen domicilio_fiscal to TEXT in empresa, proveedor, cliente';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE empresa   MODIFY domicilio_fiscal TEXT NOT NULL');
        $this->addSql('ALTER TABLE proveedor MODIFY domicilio_fiscal TEXT NULL');
        $this->addSql('ALTER TABLE cliente   MODIFY domicilio_fiscal TEXT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE empresa   MODIFY domicilio_fiscal VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE proveedor MODIFY domicilio_fiscal VARCHAR(255) NULL');
        $this->addSql('ALTER TABLE cliente   MODIFY domicilio_fiscal VARCHAR(255) NULL');
    }
}
