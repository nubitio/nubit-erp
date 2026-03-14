<?php

declare(strict_types=1);

namespace App\EInvoicing\Sale\Void;

use App\Entity\CashMovement;
use App\Entity\CashMovementCategory;
use App\Entity\CashShift;
use App\Entity\PaymentMethod;
use App\Entity\SaleRecord;
use App\Enum\CashShiftStatus;
use App\Repository\CashMovementCategoryRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class CashRefundRegistrar
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CashMovementCategoryRepository $cashMovementCategoryRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function register(SaleRecord $sale, int $userId): void
    {
        if ($sale->getTipoPago()?->getId() !== PaymentMethod::CASH) {
            return;
        }

        $cashShift = $this->entityManager->getRepository(CashShift::class)
            ->findOneBy([
                'status' => CashShiftStatus::OPEN,
                'openedBy' => $userId,
            ]);

        if (!$cashShift instanceof CashShift) {
            $this->logger->warning('No open cash shift for cash refund movement', [
                'sale_id' => $sale->getId(),
                'user_id' => $userId,
            ]);

            return;
        }

        $category = $this->cashMovementCategoryRepository->findOneBy([
            'code' => CashMovementCategory::CUSTOMER_REFUND,
        ]);

        if (!$category instanceof CashMovementCategory) {
            $this->logger->error('Cash refund category not found', [
                'sale_id' => $sale->getId(),
            ]);

            return;
        }

        $movement = new CashMovement();
        $movement->setCashShift($cashShift);
        $movement->setCategory($category);
        $movement->setType($category->getType());
        $movement->setAmount($sale->getTotal() ?? 0);
        $movement->setDescription('Anulación de venta N° ' . $sale->getSerie() . '-' . $sale->getNumero());
        $movement->setCreatedAt(new DateTimeImmutable());

        $this->entityManager->persist($movement);
    }
}
