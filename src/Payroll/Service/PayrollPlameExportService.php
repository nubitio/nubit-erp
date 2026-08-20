<?php

declare(strict_types=1);

namespace App\Payroll\Service;

use App\Payroll\Entity\Employee;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Entity\PayslipEntry;
use App\Payroll\Repository\PayslipEntryRepository;
use App\Reporting\Ple\PleWriter;
use Brick\Math\BigDecimal;

/**
 * Generates the "Estructura 18 – Trabajador: Detalle de ingresos, tributos
 * y descuentos" import file consumed by SUNAT's PDT PLAME desktop
 * application (PLAME is not a direct-submission file like PLE — see the
 * Fase 7 plan notes). Reuses PleWriter's generic pipe-delimited formatting
 * (`field`/`amount`/`record`), since the separator convention matches.
 *
 * Layout confirmed against SUNAT's official "Anexo 3: Estructura de los
 * archivos de importación de T-Registro y de la Planilla Mensual"
 * (05.07.2023, Estructura 18, page 18/31): filename
 * `ffffaaaamm###########.rem` (ffff=0601 código de formulario,
 * aaaa=año, mm=mes, ###########=RUC del empleador, 11 dígitos), one
 * pipe-delimited line PER CONCEPT per worker — not one summary line per
 * worker — with fields: tipo doc. trabajador (Tabla 3, 2 chars), número
 * doc. trabajador, código de concepto (Tabla 22, 4 digits), monto
 * devengado, monto pagado/descontado. Per the Anexo's notes, AFP-related
 * codes (601/606/608/609) and all discount codes only populate "monto
 * pagado/descontado", leaving "monto devengado" at zero.
 *
 * ⚠️ The concept codes below (Tabla 22) are NOT independently confirmed —
 * the Anexo references "Tabla 22" for the full code catalog but that
 * table itself wasn't available during this phase's research. Only
 * 0601/0606/0608 are evidenced by the Anexo's own text (it states these
 * three codes require the worker to be on the AFP/Sistema Privado de
 * Pensiones, which matches aporte obligatorio/prima de seguro/comisión).
 * The ONP and earnings codes are placeholders. MUST be validated against
 * Tabla 22 by an accountant/payroll specialist before this export is used
 * for a real PLAME declaration.
 */
final readonly class PayrollPlameExportService
{
    private const string FORM_CODE = '0601';

    // Evidenced by the Anexo's own notes (require AFP/SPP membership).
    private const string CONCEPT_AFP_MANDATORY = '0601';

    private const string CONCEPT_AFP_INSURANCE = '0606';

    private const string CONCEPT_AFP_COMMISSION = '0608';

    // NOT evidenced by the Anexo excerpt available during this phase —
    // placeholders pending Tabla 22 confirmation.
    private const string CONCEPT_EARNINGS_REGULAR = '0101';

    private const string CONCEPT_EARNINGS_GRATIFICATION = '0301';

    private const string CONCEPT_EARNINGS_CTS = '0401';

    private const string CONCEPT_ONP = '0602';

    private const string CONCEPT_BONIFICACION_EXTRAORDINARIA = '0302';

    public function __construct(
        private PayslipEntryRepository $payslipEntryRepository,
    ) {}

    /**
     * @return array{filename: string, content: string}
     */
    public function export(PayrollRun $payrollRun): array
    {
        $company = $payrollRun->getCompany();
        $period = $payrollRun->getPeriod();

        $entries = $this->payslipEntryRepository
            ->createQueryBuilder('p')
            ->andWhere('p.payrollRun = :payrollRun')
            ->setParameter('payrollRun', $payrollRun)
            ->getQuery()
            ->getResult();

        $content = '';
        foreach ($entries as $entry) {
            $employee = $entry->getEmployee();
            $docType = '01'; // Tabla 3: only DNI is modeled on Employee today.
            $docNumber = $employee->getDni();

            $earningsCode = match ($payrollRun->getType()) {
                PayrollRun::GRATIFICATION_JULY,
                PayrollRun::GRATIFICATION_DECEMBER,
                    => self::CONCEPT_EARNINGS_GRATIFICATION,
                PayrollRun::CTS_MAY, PayrollRun::CTS_NOVEMBER => self::CONCEPT_EARNINGS_CTS,
                default => self::CONCEPT_EARNINGS_REGULAR,
            };
            $content .= $this->earningsLine($docType, $docNumber, $earningsCode, $entry->getGrossSalary());
            $content .= $this->pensionDeductionLines($docType, $docNumber, $employee, $entry);
            $content .= $this->earningsLine(
                $docType,
                $docNumber,
                self::CONCEPT_BONIFICACION_EXTRAORDINARIA,
                $entry->getBonificacionExtraordinaria(),
            );

            // EsSalud is the employer's own contribution, not the worker's
            // ingreso/descuento — Estructura 18 only covers the latter
            // (consistent with the Anexo excluding the 0800/EsSalud code
            // range from this import), so it's intentionally omitted here.
        }

        return [
            'filename' => sprintf(
                '%s%s%s%s.rem',
                self::FORM_CODE,
                $period->format('Y'),
                $period->format('m'),
                $company->getRuc(),
            ),
            'content' => $content,
        ];
    }

    private function pensionDeductionLines(
        string $docType,
        string $docNumber,
        Employee $employee,
        PayslipEntry $entry,
    ): string {
        if ($employee->getPensionScheme() !== Employee::PENSION_AFP) {
            return $this->deductionLine($docType, $docNumber, self::CONCEPT_ONP, $entry->getOnpAmount());
        }

        return (
            $this->deductionLine(
                $docType,
                $docNumber,
                self::CONCEPT_AFP_MANDATORY,
                $entry->getAfpMandatoryContribution(),
            )
            . $this->deductionLine($docType, $docNumber, self::CONCEPT_AFP_INSURANCE, $entry->getAfpInsurancePremium())
            . $this->deductionLine($docType, $docNumber, self::CONCEPT_AFP_COMMISSION, $entry->getAfpCommission())
        );
    }

    private function earningsLine(string $docType, string $docNumber, string $concept, string $amount): string
    {
        if (BigDecimal::of($amount)->isZero()) {
            return '';
        }

        return PleWriter::record([
            $docType,
            $docNumber,
            $concept,
            PleWriter::amount($amount),
            PleWriter::amount($amount),
        ]);
    }

    /**
     * Per the Anexo: discount/AFP codes only populate "monto
     * pagado/descontado" (field 5), leaving "monto devengado" (field 4) at
     * zero.
     */
    private function deductionLine(string $docType, string $docNumber, string $concept, string $amount): string
    {
        if (BigDecimal::of($amount)->isZero()) {
            return '';
        }

        return PleWriter::record([$docType, $docNumber, $concept, PleWriter::amount('0'), PleWriter::amount($amount)]);
    }
}
