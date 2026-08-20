<?php

declare(strict_types=1);

namespace App\Payroll\Repository;

use App\Payroll\Entity\Employee;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Entity\PayslipEntry;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PayslipEntry>
 */
class PayslipEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PayslipEntry::class);
    }

    /**
     * Sum of REGULAR gross salary already calculated for this employee in
     * the same calendar year, strictly before $beforePeriod — the "ya
     * percibido" term in the SUNAT quinta categoría annual projection.
     * Only APPROVED/PAID runs count so a still-editable DRAFT/CALCULATED
     * run isn't double-projected into its own withholding.
     */
    public function sumRegularGrossYtd(Employee $employee, DateTimeImmutable $beforePeriod): BigDecimal
    {
        return $this->sumYtd($employee, $beforePeriod, 'p.grossSalary', [PayrollRun::REGULAR]);
    }

    /**
     * Sum of quinta categoría already withheld for this employee in the
     * same calendar year, strictly before $beforePeriod — used to net the
     * cumulative-projection method down to this month's retention.
     */
    public function sumQuintaCategoriaWithheldYtd(Employee $employee, DateTimeImmutable $beforePeriod): BigDecimal
    {
        return $this->sumYtd($employee, $beforePeriod, 'p.quintaCategoriaRetention', [PayrollRun::REGULAR]);
    }

    /**
     * @param list<string> $runTypes
     */
    private function sumYtd(
        Employee $employee,
        DateTimeImmutable $beforePeriod,
        string $field,
        array $runTypes,
    ): BigDecimal {
        $yearStart = new DateTimeImmutable(sprintf('%d-01-01', (int) $beforePeriod->format('Y')));

        $result = $this
            ->createQueryBuilder('p')
            ->select(sprintf('COALESCE(SUM(%s), 0) as total', $field))
            ->innerJoin('p.payrollRun', 'r')
            ->andWhere('p.employee = :employee')
            ->andWhere('r.type IN (:types)')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.period >= :yearStart')
            ->andWhere('r.period < :beforePeriod')
            ->setParameter('employee', $employee)
            ->setParameter('types', $runTypes)
            ->setParameter('statuses', [PayrollRun::APPROVED, PayrollRun::PAID])
            ->setParameter('yearStart', $yearStart)
            ->setParameter('beforePeriod', $beforePeriod)
            ->getQuery()
            ->getSingleScalarResult();

        return BigDecimal::of((string) $result);
    }
}
