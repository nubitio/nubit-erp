<?php

declare(strict_types=1);

namespace App\Payroll\Service;

use App\Efact\Domain\Money;
use App\Payroll\Entity\Employee;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Entity\PayslipEntry;
use App\Payroll\Repository\AfpRateRepository;
use App\Payroll\Repository\PayslipEntryRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use InvalidArgumentException;

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

    /**
     * UIT (Unidad Impositiva Tributaria) vigente — cambia cada año por
     * Decreto Supremo del MEF. S/ 5,350 corresponde al ejercicio 2025.
     * MUST be updated at the start of each fiscal year (and ideally moved
     * to a tenant-editable setting rather than a hardcoded constant) —
     * every quinta categoría figure this service produces is wrong for the
     * whole year if this value is stale.
     */
    private const string UIT_VALUE = '5350.00';

    /** Deducción fija anual de 7 UIT aplicable a rentas de trabajo (Art. 46 LIR). */
    private const int FIXED_DEDUCTION_UIT = 7;

    /**
     * Tramos progresivos acumulativos del impuesto a la renta de trabajo
     * (Art. 53 LIR), expresados en UIT sobre la renta neta (ya deducidas
     * las 7 UIT). Cada tramo es {hasta UIT (null = sin tope), tasa}.
     *
     * @var list<array{upToUit: ?int, rate: string}>
     */
    private const array INCOME_TAX_BRACKETS = [
        ['upToUit' => 5, 'rate' => '8.00'],
        ['upToUit' => 20, 'rate' => '14.00'],
        ['upToUit' => 35, 'rate' => '17.00'],
        ['upToUit' => 45, 'rate' => '20.00'],
        ['upToUit' => null, 'rate' => '30.00'],
    ];

    /**
     * Divisor mensual del procedimiento de retención de quinta categoría
     * (Reglamento de la LIR): el impuesto anual proyectado se divide entre
     * este número, y se resta lo ya retenido en el año, para hallar la
     * retención del mes. Diciembre usa 1 porque es el ajuste final.
     *
     * @var array<int, int>
     */
    private const array WITHHOLDING_DIVISOR_BY_MONTH = [
        1 => 12,
        2 => 12,
        3 => 12,
        4 => 9,
        5 => 8,
        6 => 7,
        7 => 6,
        8 => 5,
        9 => 4,
        10 => 3,
        11 => 2,
        12 => 1,
    ];

    /** Días de vacaciones que acumula un trabajador por cada mes completo laborado (30 días / año). */
    private const string VACATION_DAYS_PER_MONTH = '2.5';

    public function __construct(
        private AfpRateRepository $afpRateRepository,
        private PayslipEntryRepository $payslipEntryRepository,
    ) {}

    public function calculateRegular(Employee $employee, DateTimeImmutable $period): PayslipEntry
    {
        $grossSalary = Money::money($employee->getBaseSalary());
        $pension = $this->calculatePensionDeduction($employee, $grossSalary, $period);
        $quintaCategoria = $this->calculateQuintaCategoria($employee, $grossSalary, $period);

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
        $entry->setQuintaCategoriaRetention(Money::toString($quintaCategoria));
        $entry->setNetPay(Money::toString(Money::sub(Money::sub($grossSalary, $pension['total']), $quintaCategoria)));

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
     * Liquidación de beneficios sociales al cese: vacaciones truncas +
     * gratificación trunca + CTS trunca, cada una prorrateada desde su
     * último periodo cerrado hasta terminationDate. No incluye
     * indemnización por despido arbitrario (depende del motivo de cese,
     * que este módulo no modela) ni sueldos/comisiones pendientes de pago
     * — esas se liquidan por fuera de este cálculo.
     */
    public function calculateLiquidacion(Employee $employee): PayslipEntry
    {
        $terminationDate = $employee->getTerminationDate();
        if ($terminationDate === null) {
            throw new InvalidArgumentException('No se puede liquidar a un trabajador sin fecha de cese.');
        }

        $monthlySalary = Money::money($employee->getBaseSalary());

        // Vacaciones truncas: 2.5 días por mes completo desde la última
        // liquidación de vacaciones (o el ingreso, si nunca se liquidaron),
        // valorizadas a razón diaria de sueldo/30.
        $vacationAnchor = $employee->getLastVacationTakenAt() ?? $employee->getHireDate();
        $vacationMonths = $this->monthsWorkedInPeriod($employee, $vacationAnchor, $terminationDate);
        $vacationDays = Money::of(self::VACATION_DAYS_PER_MONTH)->multipliedBy($vacationMonths);
        $dailySalary = $monthlySalary->dividedBy(30, Money::SCALE + 4, RoundingMode::HALF_UP);
        $vacationTrunca = Money::money($dailySalary->multipliedBy($vacationDays));

        // Gratificación trunca: mismo cálculo que calculateGratification,
        // desde el inicio del semestre en curso hasta el cese (Ley 30334
        // aplica igual a la trunca, incluida la bonificación extraordinaria).
        [$semesterStart, $semesterEnd] = $this->currentSemester($terminationDate);
        $gratificationMonths = $this->monthsWorkedInPeriod($employee, $semesterStart, $semesterEnd);
        $gratificacionTrunca = Money::money($monthlySalary->multipliedBy($gratificationMonths)->dividedBy(
            6,
            Money::SCALE + 4,
            RoundingMode::HALF_UP,
        ));
        $bonificacionRate = $employee->isEpsAffiliated()
            ? self::BONIFICACION_EPS_RATE
            : self::BONIFICACION_ESSALUD_RATE;
        $gratificacionTrunca = Money::add($gratificacionTrunca, Money::mul(
            $gratificacionTrunca,
            Money::rate($bonificacionRate),
        ));

        // CTS trunca: mismo cálculo que calculateCts, desde el inicio del
        // semestre CTS en curso hasta el cese.
        [$ctsSemesterStart, $ctsSemesterEnd] = $this->currentCtsSemester($terminationDate);
        $ctsMonths = $this->monthsWorkedInPeriod($employee, $ctsSemesterStart, $ctsSemesterEnd);
        $sixthOfGratification = $monthlySalary->dividedBy(6, Money::SCALE + 4, RoundingMode::HALF_UP);
        $computableRemuneration = Money::add($monthlySalary, Money::money($sixthOfGratification));
        $ctsTrunca = Money::money($computableRemuneration->multipliedBy($ctsMonths)->dividedBy(
            12,
            Money::SCALE + 4,
            RoundingMode::HALF_UP,
        ));

        $total = Money::add(Money::add($vacationTrunca, $gratificacionTrunca), $ctsTrunca);

        $entry = new PayslipEntry();
        $entry->setEmployee($employee);
        $entry->setGrossSalary(Money::toString($total));
        $entry->setVacationTruncaAmount(Money::toString($vacationTrunca));
        $entry->setGratificacionTruncaAmount(Money::toString($gratificacionTrunca));
        $entry->setCtsTruncaAmount(Money::toString($ctsTrunca));
        // Igual que CTS regular, la liquidación de beneficios sociales no
        // está afecta a AFP/ONP/EsSalud ni a quinta categoría.
        $entry->setNetPay(Money::toString($total));

        return $entry;
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function currentSemester(DateTimeImmutable $date): array
    {
        $year = (int) $date->format('Y');

        return (
            (int) $date->format('n') <= 6
                ? [new DateTimeImmutable(sprintf('%d-01-01', $year)), new DateTimeImmutable(sprintf('%d-06-30', $year))]
                : [new DateTimeImmutable(sprintf('%d-07-01', $year)), new DateTimeImmutable(sprintf('%d-12-31', $year))]
        );
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function currentCtsSemester(DateTimeImmutable $date): array
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        return (
            $month >= 5 && $month <= 10
                ? [new DateTimeImmutable(sprintf('%d-05-01', $year)), new DateTimeImmutable(sprintf('%d-10-31', $year))]
                : [
                    new DateTimeImmutable(sprintf('%d-11-01', $month >= 11 ? $year : $year - 1)),
                    new DateTimeImmutable(sprintf('%d-04-30', $month >= 11 ? $year + 1 : $year)),
                ]
        );
    }

    /**
     * Retención mensual de renta de quinta categoría (Art. 40 y 53 LIR):
     * proyecta el ingreso anual (lo ya percibido en el año + el sueldo del
     * mes actual × meses restantes), deduce 7 UIT, aplica los tramos
     * progresivos, y divide el impuesto anual entre el divisor del mes
     * corriente — restando lo ya retenido en el año, para que la suma de
     * retenciones mensuales converja al impuesto anual real.
     *
     * Simplificación deliberada: solo proyecta la remuneración mensual
     * regular. No incorpora otros ingresos afectos (bonos, utilidades)
     * porque este módulo no los modela todavía — subestima la retención
     * para trabajadores con ingresos variables adicionales.
     */
    private function calculateQuintaCategoria(
        Employee $employee,
        BigDecimal $monthlyGross,
        DateTimeImmutable $period,
    ): BigDecimal {
        $month = (int) $period->format('n');
        $remainingMonths = BigDecimal::of(13 - $month);

        $ytdGross = Money::money($this->payslipEntryRepository->sumRegularGrossYtd($employee, $period));
        $projectedAnnual = Money::add($ytdGross, Money::mul($monthlyGross, $remainingMonths));

        $deduction = Money::mul(Money::of(self::UIT_VALUE), BigDecimal::of(self::FIXED_DEDUCTION_UIT));
        $taxableAnnual = Money::sub($projectedAnnual, $deduction);

        if ($taxableAnnual->isLessThanOrEqualTo(BigDecimal::zero())) {
            return BigDecimal::zero()->toScale(Money::SCALE);
        }

        $annualTax = $this->applyProgressiveBrackets($taxableAnnual);

        $divisor = self::WITHHOLDING_DIVISOR_BY_MONTH[$month];
        $projectedWithholding = Money::div($annualTax, BigDecimal::of($divisor), Money::SCALE);

        $alreadyWithheld = Money::money($this->payslipEntryRepository->sumQuintaCategoriaWithheldYtd(
            $employee,
            $period,
        ));
        $monthWithholding = Money::sub($projectedWithholding, $alreadyWithheld);

        return $monthWithholding->isNegative() ? BigDecimal::zero()->toScale(Money::SCALE) : $monthWithholding;
    }

    private function applyProgressiveBrackets(BigDecimal $taxableAnnual): BigDecimal
    {
        $uit = Money::of(self::UIT_VALUE);
        $tax = BigDecimal::zero()->toScale(Money::SCALE);
        $previousThreshold = BigDecimal::zero();

        foreach (self::INCOME_TAX_BRACKETS as $bracket) {
            $threshold = $bracket['upToUit'] === null ? null : Money::mul($uit, BigDecimal::of($bracket['upToUit']));

            $bracketBase = $threshold === null
                ? Money::sub($taxableAnnual, $previousThreshold)
                : Money::sub($taxableAnnual->isLessThan($threshold) ? $taxableAnnual : $threshold, $previousThreshold);

            if ($bracketBase->isPositive()) {
                $tax = Money::add($tax, Money::mul($bracketBase, Money::rate($bracket['rate'])));
            }

            if ($threshold !== null) {
                $previousThreshold = $threshold;
            }

            if ($threshold !== null && $taxableAnnual->isLessThanOrEqualTo($threshold)) {
                break;
            }
        }

        return $tax;
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
