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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sale:migrate', description: '[DEPRECATED] Legacy sale backfill over movimiento', help: <<<'TXT'
Actualiza valores de ventas en tablas legacy (movimiento).
Deprecated after POS V2 cutover; use only for controlled legacy recovery.
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
        $this->addOption(
            'force-legacy',
            null,
            InputOption::VALUE_NONE,
            'Allow execution over legacy movimiento tables (deprecated path).'
        );
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!(bool) $input->getOption('force-legacy')) {
            $io->warning('Deprecated legacy command. Re-run with --force-legacy if you explicitly need it.');
            return Command::SUCCESS;
        }

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
