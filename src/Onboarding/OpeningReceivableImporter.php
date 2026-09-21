<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Entity\User;
use App\Inventory\Entity\Warehouse;
use App\Inventory\Repository\WarehouseRepository;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use App\Sales\Service\CustomerAccountLedger;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class OpeningReceivableImporter
{
    public const int MAX_ROWS = 5_000;
    public const int FLUSH_EVERY = 250;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BranchRepository $branchRepository,
        private WarehouseRepository $warehouseRepository,
        private PaymentMethodRepository $paymentMethodRepository,
        private BranchContextServiceInterface $branchContext,
        private Security $security,
        private CustomerAccountLedger $customerAccountLedger,
        private OpeningReceivableRowImporter $rowImporter,
        private ?MessageBusInterface $messageBus = null,
    ) {}

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array{created: int, skipped: int, errors: list<array{row: int, message: string}>}
     */
    public function import(array $rows): array
    {
        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException(sprintf(
                'La importación admite como máximo %d filas por solicitud.',
                self::MAX_ROWS,
            ));
        }

        return $this->entityManager->wrapInTransaction(function () use ($rows): array {
            $tenant = $this->resolveTenantContext();
            $user = $this->security->getUser();
            $userId = $user instanceof User ? (int) $user->getId() : 0;
            $now = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));

            $created = 0;
            $skipped = 0;
            /** @var list<array{row: int, message: string}> $errors */
            $errors = [];
            /** @var list<OpeningReceivableChargeToRecord> $pendingCharges */
            $pendingCharges = [];
            /** @var array<string, true> $seenRetryKeys */
            $seenRetryKeys = [];

            foreach ($rows as $index => $raw) {
                $retryKey = $this->rowImporter->retryKeyFor($raw);
                if ($retryKey !== null && isset($seenRetryKeys[$retryKey])) {
                    ++$skipped;
                    continue;
                }

                $outcome = $this->rowImporter->import($raw, $tenant, $userId, $now);

                if ($outcome instanceof OpeningReceivableRowError) {
                    $errors[] = ['row' => $index + 1, 'message' => $outcome->message];
                    continue;
                }
                if ($outcome instanceof OpeningReceivableRowSkipped) {
                    ++$skipped;
                    continue;
                }

                ++$created;
                if ($retryKey !== null) {
                    $seenRetryKeys[$retryKey] = true;
                }
                $pendingCharges[] = $outcome;

                if (($created % self::FLUSH_EVERY) === 0) {
                    $this->entityManager->flush();
                    $this->recordPendingCharges($pendingCharges);
                    $this->entityManager->clear();

                    // clear() also detaches the shared references. Reload them before
                    // attaching the next batch so Doctrine never persists a detached
                    // branch, warehouse, or payment method.
                    $tenant = $this->resolveTenantContext();
                }
            }

            $this->entityManager->flush();
            $this->recordPendingCharges($pendingCharges);

            return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
        });
    }

    private function resolveTenantContext(): OpeningReceivableTenantContext
    {
        $branch = $this->resolveBranch();

        return new OpeningReceivableTenantContext(
            branch: $branch,
            warehouse: $this->resolveWarehouse($branch),
            paymentMethod: $this->resolvePaymentMethod(),
        );
    }

    /**
     * Customer ids for newly-created customers aren't assigned until they're
     * flushed, so charges are queued per row and only booked on the customer
     * ledger once the batch (or the final flush) has actually persisted them.
     *
     * @param list<OpeningReceivableChargeToRecord> $pendingCharges
     */
    private function recordPendingCharges(array &$pendingCharges): void
    {
        foreach ($pendingCharges as $charge) {
            $this->customerAccountLedger->recordCharge(
                customerId: (int) $charge->customer->getId(),
                saleId: $charge->saleId,
                occurredOn: $charge->occurredOn,
                amount: $charge->amount,
                description: $charge->description,
            );

            $this->messageBus?->dispatch(new EconomicEvent(
                source: EconomicEvent::SOURCE_OPENING_RECEIVABLE,
                sourceId: $charge->saleId,
                kind: EconomicEvent::OPENING_RECEIVABLE,
                occurredOn: $charge->occurredOn->format('Y-m-d'),
                description: $charge->description ?? 'Saldo inicial por cobrar',
                total: $charge->amount,
            ));
        }

        $pendingCharges = [];
    }

    private function resolveBranch(): Branch
    {
        $branchId = $this->branchContext->getCurrentBranchId();
        $branch = $branchId !== null
            ? $this->branchRepository->find($branchId)
            : $this->branchRepository->findOneBy([]);
        if (!$branch instanceof Branch) {
            throw new RuntimeException('No hay un local seleccionado para importar saldos.');
        }

        return $branch;
    }

    private function resolveWarehouse(Branch $branch): Warehouse
    {
        $warehouse = $this->warehouseRepository->findOneBy([
            'branch' => $branch,
        ]) ?? $this->warehouseRepository->findOneBy([]);
        if (!$warehouse instanceof Warehouse) {
            throw new RuntimeException('No hay un almacén para importar saldos.');
        }

        return $warehouse;
    }

    private function resolvePaymentMethod(): ?PaymentMethod
    {
        return (
            $this->paymentMethodRepository->findOneBy(['name' => 'Credito']) ?? $this->paymentMethodRepository->findBy(
                [],
                ['id' => 'ASC'],
                1,
            )[0] ?? null
        );
    }
}
