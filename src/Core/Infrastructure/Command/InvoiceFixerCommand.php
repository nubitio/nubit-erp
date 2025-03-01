<?php

namespace App\Core\Infrastructure\Command;

use App\Billing\Domain\BillConsultUseCase;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Infrastructure\Service\TenantConnectionSwitcher;
use App\Core\Infrastructure\Service\TenantManager;
use App\CreditNote\Domain\SendCreditNoteUseCase;
use App\CreditNote\Infrastructure\CreditNoteUtils;
use App\Invoice\Domain\GetOneVoucherUseCase;
use App\Invoice\Domain\InvoiceSendUseCase;
use App\Sale\Domain\CloneSaleUseCase;
use App\Sale\Domain\SaleInvoiceUseCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

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
        private readonly CloneSaleUseCase $cloneSaleUseCase,
        private readonly SaleInvoiceUseCase $saleInvoiceUseCase,
        private readonly GetOneVoucherUseCase $getOneVoucherUseCase,
        private readonly SendCreditNoteUseCase $sendCreditNoteUseCase,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setHelp('Corrección de comprobantes');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $io->title('Corrección de comprobantes');
            $tenants = $this->tenantManager->getTenants();
            $tenants = array_map(fn ($tenant): mixed => $tenant['name'], $tenants);
            /** @var string $sourceTenant */
            $sourceTenant = $io->choice('Tenant origen', $tenants);
            /** @var string $targetTenant */
            $targetTenant = $io->choice('Tenant destino', $tenants);
            while ($io->confirm('¿Desea continuar?')) {
                $this->tenantConnectionSwitcher->switchConnection($sourceTenant);
                $invoiceSeries = $io->ask('Serie de comprobante');
                $invoiceNumber = $io->ask('Número de comprobante');
                /** @var array<string, string|int|float|null> $invoice */
                $invoice = $this->connection->fetchAssociative(
                    'SELECT * FROM comprobante WHERE serie = :serie AND numero = :number',
                    [
                        'serie' => $invoiceSeries,
                        'number' => $invoiceNumber,
                    ]
                );
                $cloneInvoice = $invoice;
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
                            $this->connection->update(
                                'comprobante',
                                $invoice,
                                ['comprobante_id' => $invoice['comprobante_id']]
                            );
                            $io->success('CDR actualizado');
                        }

                        $resend = $io->confirm('¿Desea reenviar el comprobante?');
                        if ($resend) {
                            $response = $this->invoiceSendUseCase->__invoke(strval($invoice['movimiento_id']));
                            $io->success('Comprobante reenviado');

                            if (!$response->sunatResponse->isSuccess()) {
                                $replacement = $io->confirm('¿Desea enviar un comprobante de reemplazo?');
                                if ($replacement) {
                                    $sale = $this->cloneSaleUseCase->__invoke(strval($invoice['movimiento_id']));
                                    $this->saleInvoiceUseCase->__invoke(strval($sale->movimiento_id));
                                }
                            }
                        }
                    }
                }

                $void = $io->confirm('¿Desea anular el comprobante en el tenant destino?');
                if ($void) {
                    $this->voidInvoice($io, $cloneInvoice, $targetTenant);
                }
            }

            $io->success('Proceso finalizado');

            return Command::SUCCESS;
        } catch (\Exception $exception) {
            $io->error('Error: ' . $exception->getMessage());
            $io->error('Trace: ' . $exception->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * @param array<string, string|int|float|null> $cloneInvoice
     *
     * @throws Exception
     * @throws ServiceException
     */
    private function voidInvoice(SymfonyStyle $io, array $cloneInvoice, string $targetTenant): void
    {
        $voucher = $this->getOneVoucherUseCase->__invoke(strval($cloneInvoice['movimiento_id']));
        $rawDocument = json_encode($voucher);
        $this->tenantConnectionSwitcher->switchConnection($targetTenant);
        $this->connection->insert('raw_document', [
            'document' => $rawDocument,
            'type' => 'sale',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $cloneInvoice['comprobante_id'] = Uuid::v4();
        $cloneInvoice['movimiento_id'] = null;
        $cloneInvoice['raw_document_id'] = $this->connection->lastInsertId();
        $existingInvoice = $this->connection->fetchAssociative(
            'SELECT * FROM comprobante WHERE serie = :serie AND numero = :number',
            [
                'serie' => $cloneInvoice['serie'],
                'number' => $cloneInvoice['numero'],
            ]
        );
        if (!$existingInvoice) {
            $this->connection->insert('comprobante', $cloneInvoice);
        }

        /** @var string $creditNoteSeries */
        $creditNoteSeries = $io->ask('Serie de nota de crédito');
        $creditNoteUtils = new CreditNoteUtils($this->connection);
        $creditNoteNumber = $creditNoteUtils->getCreditNoteNumber($creditNoteSeries);
        $creditNote = [
            'nota_id' => Uuid::v4(),
            'raw_document_id' => $cloneInvoice['raw_document_id'],
            'fecha' => date('Y-m-d'),
            'tipo_id' => '07',
            'codigo_motivo' => '01',
            'descripcion_motivo' => 'ERROR DEL SISTEMA',
            'serie' => $creditNoteSeries,
            'correlativo' => $creditNoteNumber,
            'doc_afectado' => $cloneInvoice['serie'] . '-' . $cloneInvoice['numero'],
            'total' => $cloneInvoice['total'],
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->connection->insert('nota', $creditNote);
        $creditNoteResponse = $this->sendCreditNoteUseCase->__invoke($creditNote['nota_id']);

        if ($creditNoteResponse->sunatResponse->isSuccess()) {
            $io->success('Nota de crédito enviada');
            $io->table(['estado', 'mensaje'], [
                [
                    $creditNoteResponse->sunatResponse->getCdrResponse()?->getCode(),
                    $creditNoteResponse->sunatResponse->getCdrResponse()?->getDescription(),
                ],
            ]);
        } else {
            $io->error('Nota de crédito no enviada');
            $io->table(['estado', 'mensaje'], [
                [
                    $creditNoteResponse->sunatResponse->getError()?->getCode(),
                    $creditNoteResponse->sunatResponse->getError()?->getMessage(),
                ],
            ]);
        }
    }
}
