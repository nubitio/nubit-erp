<?php

declare(strict_types=1);

namespace App\Sales\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Entity\PaymentMethod;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use App\Sales\Dto\CollectOnAccountResult;
use App\Sales\Dto\SaleCollectOnAccountDto;
use App\Sales\Service\CollectOnAccountService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<SaleCollectOnAccountDto, CollectOnAccountResult>
 */
readonly class CollectOnAccountProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private CollectOnAccountService $collectOnAccountService,
    ) {}

    /**
     * @param SaleCollectOnAccountDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): CollectOnAccountResult {
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

        return $this->collectOnAccountService->collect(
            customerId: $data->customerId,
            paymentMethod: $paymentMethod,
            amount: (string) $data->amount,
            paidAt: $paidAt,
            reference: $data->reference,
            applications: $data->applications,
            createdBy: $userRef,
            moneyAccountId: $data->moneyAccountId,
            collectionId: $this->collectionIdFor($context['request'] ?? null, (int) $user->userId),
        );
    }

    /**
     * A stable collection id from the `Idempotency-Key` header, scoped to the user: a retry of the same
     * request resolves to the same id, which the service uses to return the first result.
     */
    private function collectionIdFor(mixed $request, int $userId): ?string
    {
        if (!$request instanceof Request) {
            return null;
        }

        $key = $request->headers->get('Idempotency-Key');
        if ($key === null || $key === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key) !== 1) {
            throw new BadRequestHttpException(
                'Idempotency-Key inválida: use 8 a 128 caracteres alfanuméricos, punto, guion o dos puntos.',
            );
        }

        return Uuid::v5(
            Uuid::fromString(Uuid::NAMESPACE_OID),
            sprintf('collect-on-account:%d:%s', $userId, $key),
        )->toRfc4122();
    }
}
