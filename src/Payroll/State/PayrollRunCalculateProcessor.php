<?php

declare(strict_types=1);

namespace App\Payroll\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Payroll\Dto\PayrollRunCalculateDto;
use App\Payroll\Entity\Employee;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Entity\PayslipEntry;
use App\Payroll\Service\PayrollCalculationService;
use App\Payroll\Service\PayrollRunService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<PayrollRunCalculateDto, PayrollRun>
 */
readonly class PayrollRunCalculateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PayrollRunService $payrollRunService,
        private PayrollCalculationService $calculationService,
    ) {}

    /**
     * @inheritdoc
     *
     * @param PayrollRunCalculateDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): PayrollRun {
        $payrollRun = $this->entityManager->find(PayrollRun::class, $data->id);
        if (!$payrollRun instanceof PayrollRun) {
            throw new NotFoundHttpException('Corrida de planilla no encontrada.');
        }

        $this->payrollRunService->transitionTo($payrollRun, PayrollRun::CALCULATED);

        $this->deleteExistingEntries($payrollRun);

        /** @var list<Employee> $employees */
        $employees = $this->entityManager
            ->getRepository(Employee::class)
            ->createQueryBuilder('e')
            ->andWhere('e.company = :company')
            ->andWhere('e.status = :status')
            ->setParameter('company', $payrollRun->getCompany())
            ->setParameter('status', Employee::ACTIVE)
            ->getQuery()
            ->getResult();

        foreach ($employees as $employee) {
            $entry = $this->calculateFor($employee, $payrollRun);
            $entry->setPayrollRun($payrollRun);
            $this->entityManager->persist($entry);
        }

        $payrollRun->setCalculatedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        return $payrollRun;
    }

    private function calculateFor(Employee $employee, PayrollRun $payrollRun): PayslipEntry
    {
        $period = $payrollRun->getPeriod();

        return match ($payrollRun->getType()) {
            PayrollRun::GRATIFICATION_JULY => $this->calculationService->calculateGratification(
                $employee,
                new DateTimeImmutable(sprintf('%d-01-01', (int) $period->format('Y'))),
                new DateTimeImmutable(sprintf('%d-06-30', (int) $period->format('Y'))),
                $period,
            ),
            PayrollRun::GRATIFICATION_DECEMBER => $this->calculationService->calculateGratification(
                $employee,
                new DateTimeImmutable(sprintf('%d-07-01', (int) $period->format('Y'))),
                new DateTimeImmutable(sprintf('%d-12-31', (int) $period->format('Y'))),
                $period,
            ),
            PayrollRun::CTS_MAY => $this->calculationService->calculateCts(
                $employee,
                new DateTimeImmutable(sprintf('%d-11-01', (int) $period->format('Y') - 1)),
                new DateTimeImmutable(sprintf('%d-04-30', (int) $period->format('Y'))),
            ),
            PayrollRun::CTS_NOVEMBER => $this->calculationService->calculateCts(
                $employee,
                new DateTimeImmutable(sprintf('%d-05-01', (int) $period->format('Y'))),
                new DateTimeImmutable(sprintf('%d-10-31', (int) $period->format('Y'))),
            ),
            default => $this->calculationService->calculateRegular($employee, $period),
        };
    }

    private function deleteExistingEntries(PayrollRun $payrollRun): void
    {
        $this->entityManager
            ->getRepository(PayslipEntry::class)
            ->createQueryBuilder('p')
            ->delete()
            ->andWhere('p.payrollRun = :payrollRun')
            ->setParameter('payrollRun', $payrollRun)
            ->getQuery()
            ->execute();
    }
}
