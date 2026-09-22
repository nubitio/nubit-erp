<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use App\Organization\Service\CompanyContext;
use App\Sales\Report\CustomerStatement\CustomerStatement;
use App\Sales\Report\CustomerStatement\CustomerStatementService;
use App\Settings\Service\TenantSettingsService;
use DateTimeImmutable;
use DateTimeZone;
use Nubit\Platform\Export\XlsExporter;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Pontedilana\PhpWeasyPrint\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Twig\Environment;

/**
 * Estado de cuenta por cliente, exportable (#366). Gated by `excel_export`
 * like the rest of the report exports.
 *
 *   GET /sales/reports/customer-statement?customer=&date-from=&date-to=&format=xlsx|pdf
 */
#[RequiresFeature('excel_export')]
final class CustomerStatementController extends AbstractController
{
    public function __construct(
        private readonly CustomerStatementService $statements,
        private readonly XlsExporter $xlsExporter,
        private readonly Environment $twig,
        private readonly string $weasyprintBinary,
        private readonly CompanyContext $companyContext,
        private readonly TenantSettingsService $tenantSettings,
    ) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $customerId = $request->query->getInt('customer');
        if ($customerId <= 0) {
            throw new BadRequestHttpException('El cliente es requerido.');
        }

        $format = strtolower((string) $request->query->get('format', 'pdf'));
        if (!in_array(needle: $format, haystack: ['pdf', 'xlsx'], strict: true)) {
            throw new BadRequestHttpException('Formato no soportado. Use pdf o xlsx.');
        }

        [$dateFrom, $dateTo] = $this->dateRange(
            (string) $request->query->get('date-from', ''),
            (string) $request->query->get('date-to', ''),
        );

        $statement = $this->statements->build($customerId, $dateFrom, $dateTo);
        $slug = preg_replace(
            pattern: '/[^A-Za-z0-9]+/',
            replacement: '-',
            subject: $statement->customerName,
        ) ?? 'cliente';
        $basename = sprintf(
            'estado-cuenta-%s-%s-a-%s',
            trim(string: $slug, characters: '-'),
            $dateFrom,
            $dateTo,
        );

        return $format === 'xlsx'
            ? $this->xlsExporter->export(
                $this->statements->toRows($statement),
                $basename . '.xlsx',
                $this->statements->columns(),
            )
            : $this->pdf($statement, $basename . '.pdf');
    }

    private function pdf(CustomerStatement $statement, string $filename): StreamedResponse
    {
        $html = $this->twig->render('report/customer_statement.html.twig', [
            'statement' => $statement,
            'company' => $this->companyContext->getCompany(),
            'printLogo' => $this->tenantSettings->isPrintLogo(),
        ]);

        $pdf = new Pdf($this->weasyprintBinary);
        $pdf->setTimeout(30);
        $bytes = $pdf->getOutputFromHtml($html);

        $response = new StreamedResponse(static function () use ($bytes): void {
            echo $bytes;
        });
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
        ));

        return $response;
    }

    /**
     * @return array{string, string} [dateFrom, dateTo] as Y-m-d, defaulting to
     *         the last 90 days in America/Lima.
     */
    private function dateRange(string $from, string $to): array
    {
        $tz = new DateTimeZone('America/Lima');
        $today = new DateTimeImmutable('now', $tz);

        $dateTo = $this->parseDate(trim($to), $tz) ?? $today;
        $dateFrom = $this->parseDate(trim($from), $tz) ?? $dateTo->modify('-90 days');

        if ($dateFrom > $dateTo) {
            throw new BadRequestHttpException('El rango de fechas es inválido: "desde" es posterior a "hasta".');
        }

        return [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')];
    }

    private function parseDate(string $value, DateTimeZone $tz): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);
        if ($date === false) {
            throw new BadRequestHttpException(sprintf(
                'Fecha inválida "%s". El formato esperado es YYYY-MM-DD.',
                $value,
            ));
        }

        return $date;
    }
}
