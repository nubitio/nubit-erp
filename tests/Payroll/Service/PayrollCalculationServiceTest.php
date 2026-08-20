<?php

declare(strict_types=1);

namespace App\Tests\Payroll\Service;

use App\Payroll\Entity\AfpRate;
use App\Payroll\Entity\Employee;
use App\Payroll\Repository\AfpRateRepository;
use App\Payroll\Repository\PayslipEntryRepository;
use App\Payroll\Service\PayrollCalculationService;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PayrollCalculationServiceTest extends TestCase
{
    public function testCalculateRegularWithOnp(): void
    {
        $employee = $this->makeEmployee(baseSalary: '3000.00', pensionScheme: Employee::PENSION_ONP);
        $service = new PayrollCalculationService($this->afpRateRepository(), $this->payslipEntryRepository());

        $entry = $service->calculateRegular($employee, new DateTimeImmutable('2026-06-01'));

        static::assertSame('3000.00', $entry->getGrossSalary());
        static::assertSame('390.00', $entry->getOnpAmount()); // 13% de 3000
        static::assertSame('0.00', $entry->getAfpMandatoryContribution());
        static::assertSame('270.00', $entry->getEssaludEmployerContribution()); // 9% de 3000, no afecta el neto
        static::assertSame('2610.00', $entry->getNetPay()); // 3000 - 390
    }

    public function testCalculateRegularWithAfp(): void
    {
        $employee = $this->makeEmployee(
            baseSalary: '3000.00',
            pensionScheme: Employee::PENSION_AFP,
            afpProvider: 'HABITAT',
        );
        $service = new PayrollCalculationService(
            $this->afpRateRepository(
                new AfpRate()
                    ->setAfpProvider('HABITAT')
                    ->setMandatoryContributionRate('10.00')
                    ->setInsurancePremiumRate('1.37')
                    ->setCommissionRate('1.47')
                    ->setEffectiveFrom(new DateTimeImmutable('2026-01-01')),
            ),
            $this->payslipEntryRepository(),
        );

        $entry = $service->calculateRegular($employee, new DateTimeImmutable('2026-06-01'));

        static::assertSame('300.00', $entry->getAfpMandatoryContribution()); // 10% de 3000
        static::assertSame('41.10', $entry->getAfpInsurancePremium()); // 1.37% de 3000
        static::assertSame('44.10', $entry->getAfpCommission()); // 1.47% de 3000
        static::assertSame('0.00', $entry->getOnpAmount());
        static::assertSame('2614.80', $entry->getNetPay()); // 3000 - (300+41.10+44.10)
    }

    public function testCalculateRegularWithAfpButNoRateConfiguredDeductsNothing(): void
    {
        $employee = $this->makeEmployee(
            baseSalary: '3000.00',
            pensionScheme: Employee::PENSION_AFP,
            afpProvider: 'INTEGRA',
        );
        $service = new PayrollCalculationService($this->afpRateRepository(), $this->payslipEntryRepository());

        $entry = $service->calculateRegular($employee, new DateTimeImmutable('2026-06-01'));

        static::assertSame('0.00', $entry->getAfpMandatoryContribution());
        static::assertSame('3000.00', $entry->getNetPay());
    }

    public function testCalculateGratificationForFullSemesterNotEpsAffiliated(): void
    {
        $employee = $this->makeEmployee(
            baseSalary: '3000.00',
            pensionScheme: Employee::PENSION_ONP,
            hireDate: '2020-01-01',
            epsAffiliated: false,
        );
        $service = new PayrollCalculationService($this->afpRateRepository(), $this->payslipEntryRepository());

        $entry = $service->calculateGratification(
            $employee,
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-06-30'),
            new DateTimeImmutable('2026-07-01'),
        );

        static::assertSame('3000.00', $entry->getGrossSalary()); // 1 sueldo completo, 6/6 meses
        static::assertSame('390.00', $entry->getOnpAmount()); // 13% de 3000
        static::assertSame('270.00', $entry->getBonificacionExtraordinaria()); // 9% (no EPS) de 3000
        static::assertSame('2880.00', $entry->getNetPay()); // (3000-390) + 270
    }

    public function testCalculateGratificationProratesForPartialSemesterEpsAffiliated(): void
    {
        $employee = $this->makeEmployee(
            baseSalary: '3000.00',
            pensionScheme: Employee::PENSION_ONP,
            hireDate: '2026-04-01',
            epsAffiliated: true,
        );
        $service = new PayrollCalculationService($this->afpRateRepository(), $this->payslipEntryRepository());

        $entry = $service->calculateGratification(
            $employee,
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-06-30'),
            new DateTimeImmutable('2026-07-01'),
        );

        static::assertSame('1500.00', $entry->getGrossSalary()); // 3 de 6 meses trabajados
        static::assertSame('195.00', $entry->getOnpAmount()); // 13% de 1500
        static::assertSame('101.25', $entry->getBonificacionExtraordinaria()); // 6.75% (EPS) de 1500
        static::assertSame('1406.25', $entry->getNetPay()); // (1500-195) + 101.25
    }

    public function testCalculateGratificationProratesFractionalMonthsOnTermination(): void
    {
        $employee = $this->makeEmployee(
            baseSalary: '3000.00',
            pensionScheme: Employee::PENSION_ONP,
            hireDate: '2020-01-01',
            terminationDate: '2026-03-15',
        );
        $service = new PayrollCalculationService($this->afpRateRepository(), $this->payslipEntryRepository());

        $entry = $service->calculateGratification(
            $employee,
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-06-30'),
            new DateTimeImmutable('2026-07-01'),
        );

        // 30/360: Ene 1 -> Mar 15 = 2 meses completos + 14/30 = 2.5 meses
        static::assertSame('1250.00', $entry->getGrossSalary()); // 3000 * 2.5 / 6
    }

    public function testCalculateCtsForFullSemester(): void
    {
        $employee = $this->makeEmployee(baseSalary: '3000.00', hireDate: '2020-01-01');
        $service = new PayrollCalculationService($this->afpRateRepository(), $this->payslipEntryRepository());

        $entry = $service->calculateCts(
            $employee,
            new DateTimeImmutable('2026-05-01'),
            new DateTimeImmutable('2026-10-31'),
        );

        // Remuneración computable = 3000 + 1/6 gratificación (500) = 3500
        // CTS = 3500 * 6/12 meses trabajados
        static::assertSame('1750.00', $entry->getGrossSalary());
        static::assertSame('1750.00', $entry->getNetPay()); // no afecta AFP/ONP/EsSalud
        static::assertSame('0.00', $entry->getOnpAmount());
    }

    public function testCalculateCtsProratesFractionalMonths(): void
    {
        $employee = $this->makeEmployee(baseSalary: '3000.00', hireDate: '2026-05-16');
        $service = new PayrollCalculationService($this->afpRateRepository(), $this->payslipEntryRepository());

        $entry = $service->calculateCts(
            $employee,
            new DateTimeImmutable('2026-05-01'),
            new DateTimeImmutable('2026-10-31'),
        );

        // 30/360: May 16 -> Oct 31 = 5.5 meses; 3500 * 5.5 / 12 = 1604.1666... -> 1604.17
        static::assertSame('1604.17', $entry->getGrossSalary());
    }

    public function testCalculateQuintaCategoriaWithheldForHighEarner(): void
    {
        // Sueldo 15000/mes, calculado en enero: proyección anual = 15000*12 = 180000
        // (13 UIT ~= 69550). Deducción 7 UIT = 37450. Renta neta = 142550.
        // Tramos: 5 UIT (26750) al 8% = 2140; 8 UIT (42800) al 14% = 5992;
        // resto (72992 hasta 15 UIT=80250... realmente 20 UIT=107000, tramo
        // hasta 20 UIT cubre hasta 107000) -> ya cubierto por segundo tramo
        // solo hasta 107000-26750=80250 de base, pero renta neta 142550 excede
        // 107000, así que sigue al tercer tramo (17%) por 142550-107000=35550.
        $employee = $this->makeEmployee(baseSalary: '15000.00', pensionScheme: Employee::PENSION_ONP);
        $service = new PayrollCalculationService($this->afpRateRepository(), $this->payslipEntryRepository());

        $entry = $service->calculateRegular($employee, new DateTimeImmutable('2026-01-01'));

        static::assertNotSame('0.00', $entry->getQuintaCategoriaRetention());
        static::assertTrue(
            BigDecimal::of($entry->getQuintaCategoriaRetention())->isPositive(),
            'Un sueldo alto proyectado por encima de 7 UIT debe generar retención de quinta categoría.',
        );
    }

    public function testCalculateQuintaCategoriaIsZeroBelowSevenUit(): void
    {
        // 3000/mes proyectado a 12 meses = 36000, muy por debajo de 7 UIT (37450).
        $employee = $this->makeEmployee(baseSalary: '3000.00', pensionScheme: Employee::PENSION_ONP);
        $service = new PayrollCalculationService($this->afpRateRepository(), $this->payslipEntryRepository());

        $entry = $service->calculateRegular($employee, new DateTimeImmutable('2026-01-01'));

        static::assertSame('0.00', $entry->getQuintaCategoriaRetention());
    }

    public function testCalculateLiquidacionSumsTruncaBenefits(): void
    {
        $employee = $this->makeEmployee(
            baseSalary: '3000.00',
            pensionScheme: Employee::PENSION_ONP,
            hireDate: '2020-01-01',
            terminationDate: '2026-03-15',
        );
        $service = new PayrollCalculationService($this->afpRateRepository(), $this->payslipEntryRepository());

        $entry = $service->calculateLiquidacion($employee);

        static::assertSame('1362.50', $entry->getGratificacionTruncaAmount()); // 1250 (2.5/6 meses) + 9% bonificación extraordinaria (no EPS)
        static::assertNotSame('0.00', $entry->getVacationTruncaAmount());
        static::assertNotSame('0.00', $entry->getCtsTruncaAmount());
        static::assertSame('0.00', $entry->getOnpAmount()); // liquidación no afecta AFP/ONP/EsSalud
    }

    public function testCalculateLiquidacionThrowsWithoutTerminationDate(): void
    {
        $employee = $this->makeEmployee(baseSalary: '3000.00');
        $service = new PayrollCalculationService($this->afpRateRepository(), $this->payslipEntryRepository());

        $this->expectException(InvalidArgumentException::class);
        $service->calculateLiquidacion($employee);
    }

    private function afpRateRepository(?AfpRate $rate = null): AfpRateRepository
    {
        $repository = $this->createMock(AfpRateRepository::class);
        $repository->method('findEffectiveRate')->willReturn($rate);

        return $repository;
    }

    private function payslipEntryRepository(): PayslipEntryRepository
    {
        $repository = $this->createMock(PayslipEntryRepository::class);
        $repository->method('sumRegularGrossYtd')->willReturn(BigDecimal::zero());
        $repository->method('sumQuintaCategoriaWithheldYtd')->willReturn(BigDecimal::zero());

        return $repository;
    }

    private function makeEmployee(
        string $baseSalary,
        string $pensionScheme = Employee::PENSION_ONP,
        ?string $afpProvider = null,
        string $hireDate = '2020-01-01',
        ?string $terminationDate = null,
        bool $epsAffiliated = false,
    ): Employee {
        $employee = new Employee()
            ->setDni('12345678')
            ->setFirstName('Juana')
            ->setLastName('Pérez')
            ->setHireDate(new DateTimeImmutable($hireDate))
            ->setBaseSalary($baseSalary)
            ->setPensionScheme($pensionScheme)
            ->setAfpProvider($afpProvider)
            ->setEpsAffiliated($epsAffiliated);

        if ($terminationDate !== null) {
            $employee->setTerminationDate(new DateTimeImmutable($terminationDate));
        }

        return $employee;
    }
}
