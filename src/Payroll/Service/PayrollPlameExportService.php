<?php

declare(strict_types=1);

namespace App\Payroll\Service;

use App\Payroll\Entity\PayrollRun;
use App\Payroll\Repository\PayslipEntryRepository;
use App\Reporting\Ple\PleWriter;

/**
 * Generates the "Estructura 18 – Detalle de Ingresos" import file consumed
 * by SUNAT's PDT PLAME desktop application (PLAME is not a direct-submission
 * file like PLE — see the Fase 7 plan notes). Reuses PleWriter's generic
 * pipe-delimited formatting (`field`/`amount`/`date`/`record`), since the
 * separator convention is the same across SUNAT's structured text imports.
 *
 * ⚠️ Column layout is a best-effort approximation built without access to
 * the current official Anexo (gob.pe blocked automated fetch during this
 * phase's research). MUST be validated against the live SUNAT Estructura 18
 * specification by an accountant/payroll specialist before this export is
 * used for a real PLAME declaration — do not submit unverified.
 */
final readonly class PayrollPlameExportService
{
    public function __construct(
        private PayslipEntryRepository $payslipEntryRepository,
    ) {}

    /**
     * @return array{filename: string, content: string}
     */
    public function export(PayrollRun $payrollRun): array
    {
        $company = $payrollRun->getCompany();
        $period = $payrollRun->getPeriod()->format('Ym');

        $entries = $this->payslipEntryRepository
            ->createQueryBuilder('p')
            ->andWhere('p.payrollRun = :payrollRun')
            ->setParameter('payrollRun', $payrollRun)
            ->getQuery()
            ->getResult();

        $content = '';
        foreach ($entries as $entry) {
            $employee = $entry->getEmployee();

            $content .= PleWriter::record([
                $period, // Periodo AAAAMM
                '6', // Tipo doc. empleador (Tabla 2: 6 = RUC)
                $company->getRuc(), // Núm. doc. empleador
                '1', // Tipo doc. trabajador (Tabla 2: 1 = DNI)
                $employee->getDni(), // Núm. doc. trabajador
                $employee->getLastName() . ' ' . $employee->getFirstName(), // Apellidos y nombres
                $this->incomeConceptCode($payrollRun->getType()), // Código de concepto de ingreso (a validar contra Tabla 18/PLAME)
                PleWriter::amount($entry->getNetPay()), // Monto del concepto
            ]);
        }

        return [
            'filename' => sprintf('PLAME_ESTRUCTURA18_%s_%s.txt', $company->getRuc(), $period),
            'content' => $content,
        ];
    }

    private function incomeConceptCode(string $payrollRunType): string
    {
        return match ($payrollRunType) {
            PayrollRun::GRATIFICATION_JULY, PayrollRun::GRATIFICATION_DECEMBER => '0800',
            PayrollRun::CTS_MAY, PayrollRun::CTS_NOVEMBER => '0900',
            default => '0100',
        };
    }
}
