<?php

declare(strict_types=1);

namespace App\AccountsPayable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\AccountsPayable\Entity\SupplierBill;
use App\Efact\Domain\Money;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles DELETE /supplier-bills/{id}.
 *
 * Voids the bill in place instead of deleting the row, so it stays visible
 * in reports and the supplier's history (mirrors PurchaseVoidProcessor).
 *
 * Guard: a bill that already has payments applied cannot be voided —
 * reverse the payments first via /supplier-bills/{id}/reverse-payment.
 * Mirrors the guard in Sales' SaleVoidUseCase (SaleException::hasAppliedPayments).
 *
 * @implements ProcessorInterface<SupplierBill, null>
 */
final readonly class SupplierBillVoidProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if ($data->getStatus() === SupplierBill::VOID) {
            throw new UnprocessableEntityHttpException('Esta factura ya fue anulada.');
        }

        if (!Money::money($data->getPaidTotal())->isZero()) {
            throw new UnprocessableEntityHttpException(
                'No se puede anular una factura con pagos aplicados. Reversa los pagos primero.',
            );
        }

        $data->setStatus(SupplierBill::VOID);
        $this->entityManager->flush();

        return null;
    }
}
