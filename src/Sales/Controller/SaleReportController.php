<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use App\Identity\Auth\BranchContextService;
use Nubit\Platform\Export\XlsExporter;
use Nubit\Platform\Report\ExportableReportInterface;
use Nubit\Platform\Report\GridFilterApplier;
use App\Efact\Util\DateHelper;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use PhpOffice\PhpSpreadsheet\Exception;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[RequiresFeature('excel_export')]
class SaleReportController extends AbstractController
{
    /** @var array<string, ExportableReportInterface> */
    private array $reportMap;

    /**
     * @param iterable<ExportableReportInterface> $saleReports Tagged iterator — all app.sale_report services.
     */
    public function __construct(
        private readonly XlsExporter $xlsExporter,
        private readonly Connection $connection,
        private readonly GridFilterApplier $gridFilterApplier,
        iterable $saleReports,
    ) {
        $this->reportMap = [];
        foreach ($saleReports as $report) {
            $this->reportMap[$report->key()] = $report;
        }
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function __invoke(Request $request, BranchContextService $branchContext): StreamedResponse
    {
        $reportKey = (string)$request->query->get('report', '');
        $dateFrom  = (string)$request->query->get('date-from', '');
        $dateTo    = (string)$request->query->get('date-to', '');

        $localId = $branchContext->getCurrentBranchId();
        if ($localId === null) {
            throw new BadRequestHttpException('Debe seleccionar una sucursal para generar el reporte.');
        }

        if ($reportKey === '') {
            throw new BadRequestHttpException('El tipo de reporte es requerido.');
        }

        $report = $this->reportMap[$reportKey] ?? null;
        if ($report === null) {
            throw new BadRequestHttpException('Reporte no encontrado.');
        }

        $params     = $this->buildDateRangeParams($localId, $dateFrom, $dateTo);
        $gridFilter = $this->gridFilterApplier->buildSql(
            (string)$request->query->get('filter', ''),
            $report->fieldMap(),
            $params,
        );

        $data = $this->connection->fetchAllAssociative($report->sql($gridFilter), $params);

        return $this->xlsExporter->export($data, $report->filename(), $report->columns());
    }

    /**
     * Build both literal business-date params and UTC instant boundaries.
     *
     * Business reports filter DATE columns with :date_from/:date_to. Timestamp-only
     * queries may use :instant_from/:instant_to. Empty bounds are open-ended
     * so report exports can use the grid filters without forcing a date range.
     *
     * @return array<string, string>
     */
    private function buildDateRangeParams(string $localId, string $dateFrom, string $dateTo): array
    {
        $utcTz = new DateTimeZone('UTC');

        $dateFrom = trim($dateFrom);
        $dateTo   = trim($dateTo);

        $fromBoundary = $dateFrom !== ''
            ? $this->parseLimaDateBoundary($dateFrom, 0, 0, 0)
            : new DateTimeImmutable('1000-01-01 00:00:00', new DateTimeZone('America/Lima'));
        $toBoundary = $dateTo !== ''
            ? $this->parseLimaDateBoundary($dateTo, 23, 59, 59)
            : new DateTimeImmutable('9999-12-31 23:59:59', new DateTimeZone('America/Lima'));

        $from = $fromBoundary->setTimezone($utcTz);
        $to   = $toBoundary->setTimezone($utcTz);

        return [
            'local_id'     => $localId,
            'date_from'    => $fromBoundary->format('Y-m-d'),
            'date_to'      => $toBoundary->format('Y-m-d'),
            'instant_from' => $from->format('Y-m-d H:i:s'),
            'instant_to'   => $to->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Parse a Y-m-d date string in the Lima timezone and set the given time boundary.
     *
     * @throws BadRequestHttpException when the string is not a valid Y-m-d date.
     */
    private function parseLimaDateBoundary(?string $dateStr, int $hour, int $minute, int $second): DateTimeImmutable
    {
        $limaTz = new DateTimeZone('America/Lima');

        if ($dateStr === null) {
            return DateHelper::todayLima()->setTime($hour, $minute, $second);
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateStr, $limaTz);
        if ($date === false) {
            throw new BadRequestHttpException(
                sprintf('Fecha inválida "%s". El formato esperado es YYYY-MM-DD.', $dateStr)
            );
        }

        return $date->setTime($hour, $minute, $second);
    }
}
