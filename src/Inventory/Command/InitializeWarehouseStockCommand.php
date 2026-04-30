<?php

declare(strict_types=1);

namespace App\Inventory\Command;

use Override;
use Nubit\Platform\Symfony\Console\TenantAwareCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:initialize-warehouse-stock',
    description: '[REMOVED] Legacy one-time stock migration command. The producto.stock column has been dropped.'
)]
/**
 * @deprecated This command read from the legacy `producto.stock` column which was
 *             removed by migration Version20260430000001_drop_producto_stock_column.
 *             Stock is now managed exclusively through stock_items (Inventory V2).
 *             The command is kept as a stub to surface a clear error if invoked.
 */
class InitializeWarehouseStockCommand extends TenantAwareCommand
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->error(
            'This command has been removed. The producto.stock column was dropped in ' .
            'Version20260430000001_drop_producto_stock_column. ' .
            'Stock is now exclusively tracked in stock_items (Inventory V2).'
        );

        return self::FAILURE;
    }
}
