<?php

declare(strict_types=1);

namespace App\Payroll\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Security\Security;
use App\Payroll\Dto\PayrollRunApproveDto;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Service\PayrollRunService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<PayrollRunApproveDto, PayrollRun>
 */
readonly class PayrollRunApproveProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private PayrollRunService $payrollRunService,
    ) {}

    /**
     * @inheritdoc
     *
     * @param PayrollRunApproveDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): PayrollRun {
        if ($this->security->connectedUser() === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $payrollRun = $this->entityManager->find(PayrollRun::class, $data->id);
        if (!$payrollRun instanceof PayrollRun) {
            throw new NotFoundHttpException('Corrida de planilla no encontrada.');
        }

        $this->payrollRunService->transitionTo($payrollRun, PayrollRun::APPROVED);
        $payrollRun->setApprovedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $payrollRun;
    }
}
