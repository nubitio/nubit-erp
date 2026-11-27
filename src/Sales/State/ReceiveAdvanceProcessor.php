<?php

declare(strict_types=1);

namespace App\Sales\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Entity\PaymentMethod;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use App\Sales\Dto\CustomerAdvanceReceipt;
use App\Sales\Dto\SaleReceiveAdvanceDto;
use App\Sales\Service\ReceiveAdvanceService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<SaleReceiveAdvanceDto, CustomerAdvanceReceipt>
 */
readonly class ReceiveAdvanceProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ReceiveAdvanceService $receiveAdvanceService,
    ) {}

    /**
     * @param SaleReceiveAdvanceDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): CustomerAdvanceReceipt {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
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

        return $this->receiveAdvanceService->receive(
            customerId: $data->customerId,
            paymentMethod: $paymentMethod,
            amount: (string) $data->amount,
            paidAt: $paidAt,
            reference: $data->reference,
            createdBy: $userRef,
            moneyAccountId: $data->moneyAccountId,
            advanceId: $this->advanceIdFor($context['request'] ?? null, (int) $user->userId),
        );
    }

    /** A stable advance id from the `Idempotency-Key` header, scoped to the user; null when the client sends none. */
    private function advanceIdFor(mixed $request, int $userId): ?string
    {
        $key = $request instanceof Request ? $request->headers->get('Idempotency-Key') : null;
        if ($key === null || $key === '') {
            return null;
        }
        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key) !== 1) {
            throw new BadRequestHttpException('Idempotency-Key inválida.');
        }

        return Uuid::v5(
            Uuid::fromString(Uuid::NAMESPACE_OID),
            sprintf('receive-advance:%d:%s', $userId, $key),
        )->toRfc4122();
    }
}
