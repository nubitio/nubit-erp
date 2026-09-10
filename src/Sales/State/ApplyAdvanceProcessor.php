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
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

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
        if ($this->security->connectedUser() === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $paidAt = $data->paidAt !== null ? new DateTimeImmutable($data->paidAt) : new DateTimeImmutable('today');

        return $this->applyAdvanceService->apply(
            customerId: $data->customerId,
            amount: (string) $data->amount,
            appliedAt: $paidAt,
            applications: $data->applications,
        );
    }
}
