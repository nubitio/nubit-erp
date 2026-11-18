<?php

declare(strict_types=1);

namespace App\Payroll\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Security\Security;
use App\Payroll\Dto\PayrollRunPayDto;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Service\PayrollAccountingService;
use App\Payroll\Service\PayrollRunService;
use App\Payroll\Service\PayrollTreasury;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<PayrollRunPayDto, PayrollRun>
 */
readonly class PayrollRunPayProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private PayrollRunService $payrollRunService,
        private ?PayrollAccountingService $payrollAccounting = null,
        private ?PayrollTreasury $payrollTreasury = null,
    ) {}

    /**
     * @inheritdoc
     *
     * @param PayrollRunPayDto $data
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

        $this->payrollRunService->transitionTo($payrollRun, PayrollRun::PAID);
        $payrollRun->setPaidAt(new DateTimeImmutable());
        if ($this->payrollTreasury !== null && $this->payrollAccounting !== null) {
            // Before the economic event and the flush: a Treasury refusal leaves the run unpaid.
            $this->payrollTreasury->record(
                $payrollRun,
                $this->payrollAccounting->netPayable($payrollRun),
                $data->paymentMethodId,
                $this->security->connectedUser()->userId,
                $data->moneyAccountId,
            );
        }

        $this->payrollAccounting?->postPayment($payrollRun, $data->paymentMethodId);

        $this->entityManager->flush();

        return $payrollRun;
    }
}
