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
 * Concept codes below are sourced from SUNAT's Tabla N° 22 "Definición
 * Conceptos PLAME" (orientacion.sunat.gob.pe, retrieved for this phase):
 * "Descuentos – Trabajador" / "Tributos y Aportes del Trabajador" section
 * for 0601/0606/0607/0608 (confirms the Estructura 18 Anexo's own note
 * that 0601/0606/0608/0609 require AFP/Sistema Privado de Pensiones
 * membership), and "Ing. Gratificaciones/Aguinaldos" + "Conceptos varios"
 * sections for 0121/0401/0904. `CONCEPT_BONIFICACION_EXTRAORDINARIA` uses
 * the "Ley 29351" code since Ley 30334 amends 29351 in place rather than
 * introducing a new code. These are still worth a final sanity check by
 * an accountant/payroll specialist before a real PLAME declaration, since
 * Tabla 22 spans 34 pages and only the entries relevant to this module
 * were reviewed.
 */
final readonly class PayrollPlameExportService
{
    private const string FORM_CODE = '0601';

    private const string CONCEPT_AFP_COMMISSION = '0601'; // Sistema Privado de Pensiones – Comisión (AFP) porcentual.

    private const string CONCEPT_AFP_INSURANCE = '0606'; // Prima de seguro AFP.

    private const string CONCEPT_ONP = '0607'; // Sistema Nacional de Pensiones – DL 19990 (ONP).

    private const string CONCEPT_AFP_MANDATORY = '0608'; // Sistema Privado de Pensiones – Aportación obligatoria.

    private const string CONCEPT_EARNINGS_REGULAR = '0121'; // Remuneración o jornal básico.

    private const string CONCEPT_EARNINGS_GRATIFICATION = '0401'; // Gratificaciones de fiestas patrias y navidad.

    private const string CONCEPT_EARNINGS_CTS = '0904'; // Compensación por tiempo de servicios (CTS).

    private const string CONCEPT_BONIFICACION_EXTRAORDINARIA = '0312'; // Bonificación extraordinaria temporal – Ley 29351 (modificada por Ley 30334).

    /**
     * Retención de renta de quinta categoría. UNVERIFIED against Tabla 22 —
     * unlike the codes above (each cross-checked against the Anexo 3 text
     * for this phase), this one is a best-effort placeholder and must be
     * confirmed against the current Tabla 22 before it's relied on for a
     * real PLAME declaration.
     */
    private const string CONCEPT_QUINTA_CATEGORIA = '0605';

    /** Vacaciones truncas — Conceptos varios / Indemnizaciones (Tabla 22). UNVERIFIED, same caveat as above. */
    private const string CONCEPT_VACACIONES_TRUNCAS = '0602';

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

            if ($payrollRun->getType() === PayrollRun::LIQUIDACION) {
                $content .= $this->earningsLine(
                    $docType,
                    $docNumber,
                    self::CONCEPT_VACACIONES_TRUNCAS,
                    $entry->getVacationTruncaAmount(),
                );
                $content .= $this->earningsLine(
                    $docType,
                    $docNumber,
                    self::CONCEPT_EARNINGS_GRATIFICATION,
                    $entry->getGratificacionTruncaAmount(),
                );
                $content .= $this->earningsLine(
                    $docType,
                    $docNumber,
                    self::CONCEPT_EARNINGS_CTS,
                    $entry->getCtsTruncaAmount(),
                );

                continue;
            }

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
            $content .= $this->deductionLine(
                $docType,
                $docNumber,
                self::CONCEPT_QUINTA_CATEGORIA,
                $entry->getQuintaCategoriaRetention(),
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
