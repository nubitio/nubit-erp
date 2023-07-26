<?php

namespace App\Core\Infrastructure\Command;

use App\Billing\Domain\BillConsultUseCase;
use App\Core\Infrastructure\Service\TenantConnectionSwitcher;
use App\Core\Infrastructure\Service\TenantManager;
use App\Invoice\Domain\InvoiceSendUseCase;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'invoice:fixer',
    description: 'Invoice fixer',
)]
class InvoiceFixerCommand extends Command
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly TenantConnectionSwitcher $tenantConnectionSwitcher,
        private readonly Connection $connection,
        private readonly BillConsultUseCase $billConsultUseCase,
        private readonly InvoiceSendUseCase $invoiceSendUseCase,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Corrección de comprobantes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $io->title('Corrección de comprobantes');
            $tenants = $this->tenantManager->getTenants();
            $tenants = array_map(fn ($tenant) => $tenant['name'], $tenants);
            /** @var string $sourceTenant */
            $sourceTenant = $io->choice('Tenant origen', $tenants);
            $targetTenant = $io->choice('Tenant destino', $tenants);
            $this->tenantConnectionSwitcher->switchConnection($sourceTenant);
            $invoiceSerie = $io->ask('Serie de comprobante');
            $invoiceNumber = $io->ask('Número de comprobante');
            /** @var array<string, string|int|float|null> $invoice */
            $invoice = $this->connection->fetchAssociative('SELECT * FROM comprobante WHERE serie = :serie AND numero = :number', [
                'serie' => $invoiceSerie,
                'number' => $invoiceNumber,
            ]);
            if (!$invoice) {
                throw new \Exception('Comprobante no encontrado');
            }
            $io->table(['fecha', 'cliente', 'serie', 'numero', 'estado sunat', 'mensaje sunat', 'total'], [
                [
                    $invoice['fecha'],
                    $invoice['cliente_id'],
                    $invoice['serie'],
                    $invoice['numero'],
                    $invoice['envio_sunat'],
                    substr(strval($invoice['mensaje_sunat']), 0, 50),
                    $invoice['total'],
                ],
            ]);
            $consultCDR = $io->confirm('¿Desea consultar el CDR?');
            if ($consultCDR) {
                $io->text('Consultando CDR');
                $cdrResponse = $this->billConsultUseCase->__invoke(strval($invoice['movimiento_id']));
                if ($cdrResponse->getCdrResponse()?->isAccepted()) {
                    $io->table(['estado', 'mensaje'], [
                        [
                            $cdrResponse->getCdrResponse()->getCode(),
                            $cdrResponse->getCdrResponse()->getDescription(),
                        ],
                    ]);
                } else {
                    $io->table(['estado', 'mensaje'], [
                        [
                            $cdrResponse->getCode(),
                            $cdrResponse->getMessage(),
                        ],
                    ]);

                    $updateCDR = $io->confirm('¿Desea actualizar el CDR?');
                    if ($updateCDR) {
                        $invoice['envio_sunat'] = $cdrResponse->getCode();
                        $invoice['mensaje_sunat'] = $cdrResponse->getMessage();
                        $this->connection->update('comprobante', $invoice, ['comprobante_id' => $invoice['comprobante_id']]);
                        $io->success('CDR actualizado');
                    }
                    $resend = $io->confirm('¿Desea reenviar el comprobante?');
                    if ($resend) {
                        $response = $this->invoiceSendUseCase->__invoke(strval($invoice['movimiento_id']));
                        $io->success('Comprobante reenviado');

                        if (!$response->sunatResponse->isSuccess()) {
                            $replacement = $io->confirm('¿Desea enviar un comprobante de reemplazo?');
                            if ($replacement) {
                            }
                            $void = $io->confirm('¿Desea anular el comprobante?');
                            if ($void) {
                            }
                        }
                    }
                }
            }

            $io->success('Success');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
