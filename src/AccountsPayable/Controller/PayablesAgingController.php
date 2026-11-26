<?php

declare(strict_types=1);

namespace App\AccountsPayable\Controller;

use App\AccountsPayable\Report\PayablesAgingCalculator;
use App\AccountsPayable\Report\PayablesAgingQuery;
use App\Identity\Auth\BranchReportScope;
use DateTimeImmutable;
use DateTimeZone;
use Nubit\Platform\Export\XlsExporter;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Antigüedad de cuentas por pagar por proveedor (días vencidos al corte).
 *
 *   GET /api/supplier-bills-aging?as_of=YYYY-MM-DD            → JSON (resumen + detalle)
 *   GET /api/supplier-bills-aging/export?view=summary|detail  → XLSX (requiere `excel_export`)
 */
final class PayablesAgingController extends AbstractController
{
    /** @var array<string, string> */
    private const array SUMMARY_COLUMNS = [
        'currency' => 'Moneda',
        'ruc' => 'RUC',
        'supplier' => 'Proveedor',
        'billCount' => 'Documentos',
        'not_due' => 'Por vencer',
        '1-30' => '1-30 días',
        '31-60' => '31-60 días',
        '61-90' => '61-90 días',
        '90+' => 'Más de 90 días',
        'total' => 'Total',
    ];

    /** @var array<string, string> */
    private const array DETAIL_COLUMNS = [
        'currency' => 'Moneda',
        'ruc' => 'RUC',
        'supplier' => 'Proveedor',
        'document' => 'Documento',
        'issueDate' => 'Emisión',
        'dueDate' => 'Vencimiento',
        'daysOverdue' => 'Días vencidos',
        'balanceDue' => 'Saldo',
    ];

    public function __construct(
        private readonly PayablesAgingQuery $aging,
        private readonly BranchReportScope $scope,
        private readonly XlsExporter $xlsExporter,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $asOf = $this->asOf($request);

        return $this->json([
            'asOf' => $asOf,
            'buckets' => PayablesAgingCalculator::BUCKETS,
            ...$this->aging->execute($this->scope->requireBranchId($request->query->getString('branchId') ?: null), $asOf),
        ]);
    }

    #[RequiresFeature('excel_export')]
    public function export(Request $request): Response
    {
        $asOf = $this->asOf($request);
        $view = (string) $request->query->get('view', 'summary');
        if (!in_array($view, ['summary', 'detail'], true)) {
            throw new BadRequestHttpException('La vista debe ser "summary" o "detail".');
        }

        $report = $this->aging->execute($this->scope->requireBranchId($request->query->getString('branchId') ?: null), $asOf);
        $columns = $this->columns($view);
        $rows = $view === 'detail' ? $report['detail'] : [...$report['suppliers'], ...$report['totals']];

        return $this->xlsExporter->export($rows, sprintf('antiguedad-cxp-%s-%s.xlsx', $view, $asOf), $columns);
    }

    /** @return array<string, string> */
    private function columns(string $view): array
    {
        return $view === 'detail' ? self::DETAIL_COLUMNS : self::SUMMARY_COLUMNS;
    }

    /** Defaults to today (America/Lima). Past dates are rejected: balances are the current ones. */
    private function asOf(Request $request): string
    {
        $tz = new DateTimeZone('America/Lima');
        $today = new DateTimeImmutable('today', $tz);
        $raw = trim((string) $request->query->get('as_of', ''));
        if ($raw === '') {
            return $today->format('Y-m-d');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $tz);
        if ($date === false) {
            throw new BadRequestHttpException(sprintf('Fecha inválida "%s". El formato esperado es YYYY-MM-DD.', $raw));
        }
        if ($date < $today) {
            throw new BadRequestHttpException(
                'La fecha de corte no puede ser anterior a hoy: los saldos son los vigentes.',
            );
        }

        return $date->format('Y-m-d');
    }
}
