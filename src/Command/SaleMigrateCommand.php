<?php

declare(strict_types=1);

namespace App\Command;

use Override;
use Exception;
use App\Core\Application\Command\TenantAwareCommand;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sale:migrate', description: 'Migrate sales', help: <<<'TXT'
Actualiza valores de las ventas
TXT)]
class SaleMigrateCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $io->title('Actualizando valores de las ventas');
            $io->text('Esto puede tardar unos minutos');
            $this->update($io);
            $io->success('Success');

            return Command::SUCCESS;
        } catch (Exception $exception) {
            $io->error('Error: ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function update(SymfonyStyle $io): void
    {
        $pending = $this->connection->fetchAllAssociative('select * from movimiento where tipo_id = 2 and numero > 0');
        $progressBar = $io->createProgressBar(count($pending));
        $progressBar->start();
        foreach ($pending as $sale) {
            $progressBar->setMessage('Updating sale ' . $sale['movimiento_id']);
            $invoice = $this->connection->fetchAssociative(
                'SELECT * FROM comprobante WHERE movimiento_id = ?',
                [$sale['movimiento_id']]
            );
            $receipt = null;
            if ($invoice) {
                $receipt = $invoice['serie'] . '-' . $invoice['numero'];
            }

            $subtotal = null;
            $total = null;
            $saleDetails = $this->connection->fetchAllAssociative(
                'SELECT * FROM movimiento_detalle WHERE movimiento_id = ?',
                [$sale['movimiento_id']]
            );
            foreach ($saleDetails as $detail) {
                $subtotal += $detail['precio_total'];
            }

            $discount = $subtotal * $sale['descuento'] / 100;
            $total = $subtotal - $discount;

            $this->connection->executeStatement(
                '
                update
                    movimiento
                set
                   invoice = ?,
                   subtotal = ?,
                   total = ?
                where
                    movimiento_id = ?',
                [
                    $receipt,
                    $subtotal,
                    $total,
                    $sale['movimiento_id'],
                ]
            );
            $progressBar->advance();
        }

        $progressBar->finish();
    }
}
