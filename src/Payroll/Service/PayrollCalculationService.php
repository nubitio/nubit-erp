<?php

declare(strict_types=1);

namespace App\Payroll\Service;

use App\Efact\Domain\Money;
use App\Payroll\Entity\Employee;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Entity\PayslipEntry;
use App\Payroll\Repository\AfpRateRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;

/**
 * Computes PayslipEntry breakdowns per PayrollRun.type. AFP commission
 * rates come from AfpRate (editable data, varies per AFP and over time —
 * see Fase 7 plan notes). ONP and EsSalud rates are flat, statutory, and
 * far more stable than AFP commissions, so they're kept as constants here
 * — still subject to the same "verify before production use" caveat as
 * everything else in this module.
 *
 * Gratificación/CTS use a 30/360 day-count (each month counted as exactly
 * 30 days) to prorate partial months worked within the semester — the
 * standard convention for these Peruvian labor-law computations.
 */
final readonly class PayrollCalculationService
{
    /** ONP: tasa fija de aporte al Sistema Nacional de Pensiones (Ley 19990). */
    private const string ONP_RATE = '13.00';

    /** EsSalud: aporte del empleador, no se descuenta del trabajador. */
    private const string ESSALUD_RATE = '9.00';

    /** Bonificación extraordinaria (Ley 30334) sobre la gratificación bruta. */
    private const string BONIFICACION_ESSALUD_RATE = '9.00';

    private const string BONIFICACION_EPS_RATE = '6.75';

    public function __construct(
        private AfpRateRepository $afpRateRepository,
    ) {}

    public function calculateRegular(Employee $employee, DateTimeImmutable $period): PayslipEntry
    {
        $grossSalary = Money::money($employee->getBaseSalary());
        $pension = $this->calculatePensionDeduction($employee, $grossSalary, $period);

        $entry = new PayslipEntry();
        $entry->setEmployee($employee);
        $entry->setGrossSalary(Money::toString($grossSalary));
        $entry->setAfpMandatoryContribution(Money::toString($pension['afpMandatoryContribution']));
        $entry->setAfpInsurancePremium(Money::toString($pension['afpInsurancePremium']));
        $entry->setAfpCommission(Money::toString($pension['afpCommission']));
        $entry->setOnpAmount(Money::toString($pension['onpAmount']));
        $entry->setEssaludEmployerContribution(Money::toString(Money::mul(
            $grossSalary,
            Money::rate(self::ESSALUD_RATE),
        )));
        $entry->setNetPay(Money::toString(Money::sub($grossSalary, $pension['total'])));

        return $entry;
    }

    /**
     * $semesterStart/$semesterEnd bound the 6-month period the gratificación
     * covers (Jan–Jun for GRATIFICATION_JULY, Jul–Dec for
     * GRATIFICATION_DECEMBER) — the caller derives these from
     * PayrollRun::type, see PayrollRunCalculateProcessor.
     */
    public function calculateGratification(
        Employee $employee,
        DateTimeImmutable $semesterStart,
        DateTimeImmutable $semesterEnd,
        DateTimeImmutable $period,
    ): PayslipEntry {
        $months = $this->monthsWorkedInPeriod($employee, $semesterStart, $semesterEnd);
        $monthlySalary = Money::money($employee->getBaseSalary());
        $gratification = Money::money($monthlySalary->multipliedBy($months)->dividedBy(
            6,
            Money::SCALE + 4,
            RoundingMode::HALF_UP,
        ));

        $bonificacionRate = $employee->isEpsAffiliated()
            ? self::BONIFICACION_EPS_RATE
            : self::BONIFICACION_ESSALUD_RATE;
        $bonificacion = Money::mul($gratification, Money::rate($bonificacionRate));

        $pension = $this->calculatePensionDeduction($employee, $gratification, $period);

        $entry = new PayslipEntry();
        $entry->setEmployee($employee);
        $entry->setGrossSalary(Money::toString($gratification));
        $entry->setAfpMandatoryContribution(Money::toString($pension['afpMandatoryContribution']));
        $entry->setAfpInsurancePremium(Money::toString($pension['afpInsurancePremium']));
        $entry->setAfpCommission(Money::toString($pension['afpCommission']));
        $entry->setOnpAmount(Money::toString($pension['onpAmount']));
        $entry->setBonificacionExtraordinaria(Money::toString($bonificacion));
        $entry->setNetPay(Money::toString(Money::add(Money::sub($gratification, $pension['total']), $bonificacion)));

        return $entry;
    }

    /**
     * $semesterStart/$semesterEnd bound the semester the CTS deposit
     * covers (Nov(y-1)–Apr for CTS_MAY, May–Oct for CTS_NOVEMBER).
     */
    public function calculateCts(
        Employee $employee,
        DateTimeImmutable $semesterStart,
        DateTimeImmutable $semesterEnd,
    ): PayslipEntry {
        $months = $this->monthsWorkedInPeriod($employee, $semesterStart, $semesterEnd);
        $monthlySalary = Money::money($employee->getBaseSalary());

        // Remuneración computable para CTS = sueldo + 1/6 de una gratificación
        // completa (Decreto Supremo 001-97-TR) — se asume gratificación
        // completa (un sueldo) como aproximación, sin trackear gratificaciones
        // truncas del semestre.
        $sixthOfGratification = $monthlySalary->dividedBy(6, Money::SCALE + 4, RoundingMode::HALF_UP);
        $computableRemuneration = Money::add($monthlySalary, Money::money($sixthOfGratification));

        $cts = Money::money($computableRemuneration->multipliedBy($months)->dividedBy(
            12,
            Money::SCALE + 4,
            RoundingMode::HALF_UP,
        ));

        $entry = new PayslipEntry();
        $entry->setEmployee($employee);
        $entry->setGrossSalary(Money::toString($cts));
        // CTS no está afecta a AFP/ONP/EsSalud — se deposita íntegra en la
        // cuenta CTS del trabajador.
        $entry->setNetPay(Money::toString($cts));

        return $entry;
    }

    /**
     * @return array{afpMandatoryContribution: BigDecimal, afpInsurancePremium: BigDecimal, afpCommission: BigDecimal, onpAmount: BigDecimal, total: BigDecimal}
     */
    private function calculatePensionDeduction(Employee $employee, BigDecimal $base, DateTimeImmutable $period): array
    {
        $zero = BigDecimal::zero()->toScale(Money::SCALE);

        if ($employee->getPensionScheme() === Employee::PENSION_ONP) {
            $onp = Money::mul($base, Money::rate(self::ONP_RATE));

            return [
                'afpMandatoryContribution' => $zero,
                'afpInsurancePremium' => $zero,
                'afpCommission' => $zero,
                'onpAmount' => $onp,
                'total' => $onp,
            ];
        }

        $rate = $employee->getAfpProvider() !== null
            ? $this->afpRateRepository->findEffectiveRate($employee->getAfpProvider(), $period)
            : null;

        if ($rate === null) {
            return [
                'afpMandatoryContribution' => $zero,
                'afpInsurancePremium' => $zero,
                'afpCommission' => $zero,
                'onpAmount' => $zero,
                'total' => $zero,
            ];
        }

        $mandatory = Money::mul($base, Money::rate($rate->getMandatoryContributionRate()));
        $insurance = Money::mul($base, Money::rate($rate->getInsurancePremiumRate()));
        $commission = Money::mul($base, Money::rate($rate->getCommissionRate()));
        $total = Money::add(Money::add($mandatory, $insurance), $commission);

        return [
            'afpMandatoryContribution' => $mandatory,
            'afpInsurancePremium' => $insurance,
            'afpCommission' => $commission,
            'onpAmount' => $zero,
            'total' => $total,
        ];
    }

    /**
     * Months worked within [$periodStart, $periodEnd], bounded by the
     * employee's hireDate/terminationDate, using a 30/360 day count
     * (each calendar month treated as exactly 30 days). Capped at the
     * number of months in the period.
     */
    private function monthsWorkedInPeriod(
        Employee $employee,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
    ): BigDecimal {
        $effectiveStart = $employee->getHireDate() > $periodStart ? $employee->getHireDate() : $periodStart;
        $effectiveEnd =
            $employee->getTerminationDate() !== null && $employee->getTerminationDate() < $periodEnd
                ? $employee->getTerminationDate()
                : $periodEnd;

        if ($effectiveStart > $effectiveEnd) {
            return BigDecimal::zero();
        }

        $days360 = $this->days360($effectiveStart, $effectiveEnd);
        $maxMonths = $this->days360($periodStart, $periodEnd)->dividedBy(30, 6, RoundingMode::HALF_UP);
        $months = $days360->dividedBy(30, 6, RoundingMode::HALF_UP);

        return $months->isGreaterThan($maxMonths) ? $maxMonths : $months;
    }

    /**
     * Inclusive day count between two dates under the 30/360 convention
     * (each month is exactly 30 days, day-of-month capped at 30).
     */
    private function days360(DateTimeImmutable $start, DateTimeImmutable $end): BigDecimal
    {
        $d1 = min((int) $start->format('d'), 30);
        $d2 = min((int) $end->format('d'), 30);

        $totalDays =
            (((int) $end->format('Y') - (int) $start->format('Y')) * 360)
                + (((int) $end->format('m') - (int) $start->format('m')) * 30)
                + $d2
                - $d1
            + 1;

        return BigDecimal::of(max(0, $totalDays));
    }
}
