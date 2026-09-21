<?php

declare(strict_types=1);

namespace App\AccountsPayable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\AccountsPayable\Dto\SupplierBillReversePaymentDto;
use App\AccountsPayable\Entity\SupplierBill;
use App\AccountsPayable\Entity\SupplierPayment;
use App\AccountsPayable\Service\SupplierPaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<SupplierBillReversePaymentDto, SupplierBill>
 */
readonly class SupplierBillReversePaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SupplierPaymentService $supplierPaymentService,
    ) {}

    /**
     * @inheritdoc
     *
     * @param SupplierBillReversePaymentDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): SupplierBill {
        $bill = $this->entityManager->find(SupplierBill::class, $data->id);
        if (!$bill instanceof SupplierBill) {
            throw new NotFoundHttpException('Factura de proveedor no encontrada.');
        }

        $payment = $this->entityManager->find(SupplierPayment::class, $data->paymentId);
        if (!$payment instanceof SupplierPayment) {
            throw new NotFoundHttpException('Pago no encontrado.');
        }

        $this->supplierPaymentService->reversePayment($bill, $payment);

        return $bill;
    }
}
