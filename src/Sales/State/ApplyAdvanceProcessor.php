<?php

declare(strict_types=1);

namespace App\Sales\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Security\Security;
use App\Sales\Dto\AdvanceApplicationResult;
use App\Sales\Dto\SaleApplyAdvanceDto;
use App\Sales\Service\ApplyAdvanceService;
use DateTimeImmutable;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<SaleApplyAdvanceDto, AdvanceApplicationResult>
 */
readonly class ApplyAdvanceProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ApplyAdvanceService $applyAdvanceService,
    ) {}

    /**
     * @param SaleApplyAdvanceDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): AdvanceApplicationResult {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $paidAt = $data->paidAt !== null ? new DateTimeImmutable($data->paidAt) : new DateTimeImmutable('today');

        return $this->applyAdvanceService->apply(
            customerId: $data->customerId,
            amount: (string) $data->amount,
            appliedAt: $paidAt,
            applications: $data->applications,
            applicationId: $this->applicationIdFor($context['request'] ?? null, (int) $user->userId),
        );
    }

    /** A stable application id from the `Idempotency-Key` header, scoped to the user; null when none is sent. */
    private function applicationIdFor(mixed $request, int $userId): ?string
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
            sprintf('apply-advance:%d:%s', $userId, $key),
        )->toRfc4122();
    }
}
