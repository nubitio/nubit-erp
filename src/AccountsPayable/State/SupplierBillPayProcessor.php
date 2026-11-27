<?php

declare(strict_types=1);

namespace App\AccountsPayable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\AccountsPayable\Dto\SupplierBillPayDto;
use App\AccountsPayable\Entity\SupplierBill;
use App\AccountsPayable\Service\SupplierPaymentService;
use App\Catalog\Entity\PaymentMethod;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<SupplierBillPayDto, SupplierBill>
 */
readonly class SupplierBillPayProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private SupplierPaymentService $supplierPaymentService,
    ) {}

    /**
     * @inheritdoc
     *
     * @param SupplierBillPayDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): SupplierBill {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $bill = $this->entityManager->find(SupplierBill::class, $data->id);
        if (!$bill instanceof SupplierBill) {
            throw new NotFoundHttpException('Factura de proveedor no encontrada.');
        }

        $paymentMethod = $this->entityManager->find(PaymentMethod::class, $data->paymentMethodId);
        if (!$paymentMethod instanceof PaymentMethod) {
            throw new NotFoundHttpException('Método de pago no encontrado.');
        }

        $userRef = $this->entityManager->getReference(User::class, $user->userId);
        if (!$userRef instanceof User) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        $paidAt = $data->paidAt !== null ? new DateTimeImmutable($data->paidAt) : new DateTimeImmutable('today');

        $this->supplierPaymentService->registerPayment(
            $bill,
            $paymentMethod,
            (string) $data->amount,
            $paidAt,
            $data->reference,
            $userRef,
            $data->moneyAccountId,
            $this->idempotencyKeyFor($context['request'] ?? null, (int) $user->userId),
        );

        return $bill;
    }

    /** A stable id from the `Idempotency-Key` header, scoped to the user; null when the client sends none. */
    private function idempotencyKeyFor(mixed $request, int $userId): ?string
    {
        $key = $request instanceof Request ? $request->headers->get('Idempotency-Key') : null;
        if ($key === null || $key === '') {
            return null;
        }
        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key) !== 1) {
            throw new BadRequestHttpException('Idempotency-Key inválida.');
        }

        return Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_OID), sprintf('supplier-pay:%d:%s', $userId, $key))
            ->toRfc4122();
    }
}
