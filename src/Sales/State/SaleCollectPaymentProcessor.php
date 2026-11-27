<?php

declare(strict_types=1);

namespace App\Sales\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Entity\PaymentMethod;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use App\Sales\Dto\SaleCollectPaymentDto;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\SalePaymentService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<SaleCollectPaymentDto, SaleRecord>
 */
readonly class SaleCollectPaymentProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private SalePaymentService $salePaymentService,
    ) {}

    /**
     * @inheritdoc
     *
     * @param SaleCollectPaymentDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): SaleRecord {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $sale = $this->entityManager->find(SaleRecord::class, $data->id);
        if (!$sale instanceof SaleRecord) {
            throw new NotFoundHttpException('Venta no encontrada.');
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

        $this->salePaymentService->registerPayment(
            $sale,
            $paymentMethod,
            (string) $data->amount,
            $paidAt,
            $data->reference,
            $userRef,
            $data->moneyAccountId,
            $this->idempotencyKeyFor($context['request'] ?? null, (int) $user->userId),
        );

        return $sale;
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

        return Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_OID), sprintf('sale-collect:%d:%s', $userId, $key))
            ->toRfc4122();
    }
}
