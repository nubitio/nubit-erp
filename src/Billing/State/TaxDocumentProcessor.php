<?php

declare(strict_types=1);

namespace App\Billing\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Service\CreditNoteLinePreparer;
use App\Billing\Service\CreditNoteStockReverserInterface;
use App\Billing\Shared\Enum\CreditNoteReason;
use App\Identity\Activity\SalesActivityInterface;
use App\Identity\Auth\OperationalBranchGuard;
use App\Identity\Entity\User;
use App\Sales\Service\CreditNoteReceivableAdjuster;
use App\Sales\State\SalesWriteGateway;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ServiceException;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
readonly class TaxDocumentProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $persistProcessor
     * @param ProcessorInterface<mixed, mixed> $removeProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private OperationalBranchGuard $branchGuard,
        private TaxDocumentRepository $taxDocumentRepository,
        private SalesWriteGateway $salesWriteGateway,
        private EntityManagerInterface $entityManager,
        private CreditNoteStockReverserInterface $creditNoteStockReverser,
        private CreditNoteLinePreparer $creditNoteLinePreparer,
        private Security $security,
        private SalesActivityInterface $activity,
        private ?CreditNoteReceivableAdjuster $receivableAdjuster = null,
    ) {}

    /**
     * @inheritdoc
     *
     * @param TaxDocument $data
     *
     * @throws ServiceException
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $isNew = !$data->getId();
        $references = [];
        if (!$isNew) {
            $references['branch'] = $data->getBranch();
        }
        if (($reference = $data->getSaleRecord()) !== null) {
            $references['saleRecord'] = $reference->getBranch();
        }
        $branch = $this->branchGuard->requireBranch($references);
        $branchId = (string) $branch->getId();

        if ($operation instanceof DeleteOperationInterface) {
            if ($data->isProcessed()) {
                throw new ServiceException('No se puede eliminar un documento procesado.');
            }

            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        $lockName = $this->acquireCreditNoteLock($isNew ? $data : null);
        $connection = $lockName !== null ? $this->entityManager->getConnection() : null;
        $transactionConnection = $connection !== null && !$connection->isTransactionActive() ? $connection : null;
        $transactionConnection?->beginTransaction();

        try {
            if ($isNew && $data->getReasonCode() != null) {
                $reason = CreditNoteReason::tryFrom((string) $data->getReasonCode());
                if ($reason === null) {
                    throw new ServiceException('El motivo de la nota de crédito no es válido (catálogo SUNAT 09).');
                }
                // Source the official description from catalog 09 when the caller did
                // not provide one, so the value sent to SUNAT is always canonical.
                if (in_array(trim((string) $data->getReasonDescription()), ['', '0'], true)) {
                    $data->setReasonDescription($reason->description());
                }

                $related = $data->getRelatedTaxDocument();
                if ($related === null) {
                    throw new ServiceException('Debe seleccionar el comprobante afectado.');
                }
                $data->setDocumentType('07');
                $data->setAffectedDocument($related->getFullNumber());
                $data->setCustomer($related->getCustomer());
                $data->setTotal($related->getTotal());
                if ($related->isVoided()) {
                    throw new ServiceException('No se puede generar nota de crédito para un comprobante anulado.');
                }
                if ($related->getSaleRecord()?->getStatus() === 'VOIDED') {
                    throw new ServiceException('No se puede generar otra nota de crédito para una venta anulada.');
                }
                if ($this->taxDocumentRepository->hasAnnullingCreditNote($related)) {
                    throw new ServiceException(
                        'No se puede generar otra nota de crédito para un comprobante ya anulado.',
                    );
                }
                $relatedBranchId = $related->getBranch()?->getId() ?? $related->getSaleRecord()?->getBranch()?->getId();
                if ($branchId !== $relatedBranchId) {
                    throw new ServiceException('El comprobante afectado no pertenece a la sucursal actual.');
                }
                $affectedSeries = $related->getSeries();
                $seriesResult = $this->taxDocumentRepository->getTaxDocumentSeriesByBranch(
                    $branchId,
                    '07',
                    $affectedSeries,
                );
                $number = $this->taxDocumentRepository->getNextSequenceNumber($branchId, '07');
                if ($seriesResult === null || $seriesResult->associatedSeries != $affectedSeries) {
                    throw new ServiceException(
                        'La serie de la nota de crédito no coincide con la serie del documento afectado.',
                    );
                }
                $data->setSeries($seriesResult->series);
                $data->setNumber((string) $number);
                $this->creditNoteLinePreparer->prepare($data);

                // Propagate sale reference from related document to new credit note
                if ($related->getSaleRecord() !== null) {
                    $data->setSaleRecord($related->getSaleRecord());
                }
            }

            if ($isNew) {
                $data->setCreatedAt(new DateTime());
                $data->setCreatedByUserId($this->currentUserId());

                $data->setBranch($branch);
            } else {
                if ($data->isProcessed()) {
                    throw new ServiceException('No se puede actualizar un documento procesado.');
                }
                $data->setUpdatedAt(new DateTime());
            }

            $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

            // If this is a new credit note with a void reason code, mark the linked sale as VOIDED.
            if ($isNew && $data->getReasonCode() !== null) {
                $this->voidSaleIfCreditNoteAnnuls($data);
                $this->creditNoteStockReverser->reverse($data);
                $this->adjustReceivable($data);
            }

            $transactionConnection?->commit();

            if ($isNew && $data->getReasonCode() !== null) {
                $this->recordCreditNoteActivity($data);
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($transactionConnection !== null && $transactionConnection->isTransactionActive()) {
                $transactionConnection->rollBack();
            }

            throw $exception;
        } finally {
            if ($lockName !== null && $connection !== null) {
                $connection->fetchOne('SELECT RELEASE_LOCK(:lockName)', ['lockName' => $lockName]);
            }
        }
    }

    /**
     * When a credit note annuls the affected document (reason codes 01, 02, 06),
     * mark the linked SaleRecord as VOIDED — both in the ORM entity and in the
     * sales V2 table — mirroring what SaleVoidUseCase does for Comunicación de Baja.
     */
    private function voidSaleIfCreditNoteAnnuls(TaxDocument $creditNote): void
    {
        $reason = CreditNoteReason::tryFrom((string) $creditNote->getReasonCode());
        if ($reason === null || !$reason->annulsDocument()) {
            return;
        }

        $saleRecord = $creditNote->getSaleRecord();
        if ($saleRecord === null) {
            return;
        }

        if ($saleRecord->getStatus() === 'VOIDED') {
            return;
        }

        $now = new \DateTimeImmutable();
        $voidReason = 'Nota de crédito: ' . $creditNote->getFullNumber();
        $saleRecord->setStatus('VOIDED');
        $saleRecord->setDateUpdated($now);
        $saleRecord->recordVoid($this->currentUserId(), $now, $voidReason);
        $this->entityManager->flush();

        try {
            $this->salesWriteGateway->markVoided($saleRecord, $voidReason, $this->currentUserId());
        } catch (\Throwable) {
            // V2 sync failure is non-fatal here; the ORM entity is already updated.
        }
    }

    /** A credit note lowers the credit sale's open balance; what was already paid becomes a saldo a favor (G-04). */
    private function adjustReceivable(TaxDocument $creditNote): void
    {
        $sale = $creditNote->getSaleRecord();
        $id = $creditNote->getId();
        if ($this->receivableAdjuster === null || $sale === null || $id === null) {
            return;
        }

        $this->receivableAdjuster->apply(
            $sale,
            $creditNote->getTotal(),
            $id,
            $creditNote->getFullNumber(),
            new \DateTimeImmutable(),
        );
    }

    private function currentUserId(): ?int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getId() : null;
    }

    private function recordCreditNoteActivity(TaxDocument $creditNote): void
    {
        $this->activity->creditNoteIssued(
            $creditNote->getFullNumber(),
            $creditNote->getAffectedDocument(),
            $creditNote->getReasonDescription(),
        );
    }

    private function acquireCreditNoteLock(?TaxDocument $document): ?string
    {
        if ($document === null || $document->getReasonCode() === null) {
            return null;
        }

        $related = $document->getRelatedTaxDocument();
        if (!$related instanceof TaxDocument) {
            return null;
        }
        $relatedId = $related->getId();
        if ($relatedId === null) {
            return null;
        }

        $lockName = 'credit-note:' . $relatedId;
        $acquired = $this->entityManager->getConnection()->fetchOne('SELECT GET_LOCK(:lockName, 5)', [
            'lockName' => $lockName,
        ]);
        if ((int) $acquired !== 1) {
            throw new ServiceException('No se pudo bloquear el comprobante para procesar la nota de crédito.');
        }

        return $lockName;
    }
}
