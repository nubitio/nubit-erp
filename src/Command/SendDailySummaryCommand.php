<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Service\TenantNotificationService;
use Override;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use RuntimeException;
use App\Core\Application\Command\TenantAwareCommand;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand(name: 'notify:daily-summary', description: 'Send daily summary to all customers', help: <<<'TXT'
Send daily summary to all customers
TXT)]
class SendDailySummaryCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantNotificationService $notificationService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Get company Name
            $companyName = $this->connection->fetchOne(
                'SELECT nombre_comercial FROM empresa LIMIT 1'
            );

            // Keep day boundaries aligned with operational reporting timezone.
            $today = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('Y-m-d');

            // Get sales count for today from Sales V2.
            $salesCount = (int)$this->connection->fetchOne(
                "SELECT COUNT(*)
                 FROM sales s
                 WHERE DATE(CONVERT_TZ(s.issued_at, 'UTC', 'America/Lima')) = ?",
                [$today]
            );

            // For simplicity, we'll use default values for customer metrics
            // since we don't know the exact schema
            $newCustomersCount = 0;
            $recurringCustomersCount = 0;

            // Get invoices sent to SUNAT count for today
            $invoicesSentCount = (int)$this->connection->fetchOne(
                "SELECT COUNT(*) FROM tax_document
                 WHERE document_type IN ('01', '03')
                   AND DATE(CONVERT_TZ(issue_date, 'UTC', 'America/Lima')) = ?",
                [$today]
            );
            $invoicesAcceptedCount = (int)$this->connection->fetchOne(
                "SELECT COUNT(*) FROM tax_document
                 WHERE document_type IN ('01', '03')
                   AND DATE(CONVERT_TZ(issue_date, 'UTC', 'America/Lima')) = ?
                   AND sunat_status = '0'",
                [$today]
            );
            $invoicesRejectedCount = (int)$this->connection->fetchOne(
                "SELECT COUNT(*) FROM tax_document
                 WHERE document_type IN ('01', '03')
                   AND DATE(CONVERT_TZ(issue_date, 'UTC', 'America/Lima')) = ?
                   AND sunat_status REGEXP '^[0-9]+$'
                   AND CAST(sunat_status AS UNSIGNED) BETWEEN 2000 AND 3999",
                [$today]
            );
            $invoicesPendingCount = (int)$this->connection->fetchOne(
                "SELECT COUNT(*) FROM tax_document
                 WHERE document_type IN ('01', '03')
                   AND DATE(CONVERT_TZ(issue_date, 'UTC', 'America/Lima')) = ?
                   AND (sunat_status IS NULL OR sunat_status = '' OR sunat_status = 'pendiente')",
                [$today]
            );
            $invoicesTechnicalErrorCount = (int)$this->connection->fetchOne(
                "SELECT COUNT(*) FROM tax_document
                 WHERE document_type IN ('01', '03')
                   AND DATE(CONVERT_TZ(issue_date, 'UTC', 'America/Lima')) = ?
                   AND sunat_status IS NOT NULL
                   AND sunat_status <> ''
                   AND sunat_status <> 'pendiente'
                   AND sunat_status <> '0'
                   AND NOT (
                       sunat_status REGEXP '^[0-9]+$'
                       AND CAST(sunat_status AS UNSIGNED) BETWEEN 2000 AND 3999
                   )",
                [$today]
            );
            $cancellationsCount = (int)$this->connection->fetchOne(
                "SELECT COUNT(*) FROM tax_document t
                 INNER JOIN sales s ON (
                     (s.legacy_movimiento_id IS NOT NULL AND s.legacy_movimiento_id = t.movimiento_id)
                     OR
                     (t.sale_id IS NOT NULL AND t.sale_id = s.id)
                 )
                 WHERE t.document_type IN ('01', '03')
                   AND s.status = 'VOIDED'
                   AND DATE(CONVERT_TZ(t.issue_date, 'UTC', 'America/Lima')) = ?",
                [$today]
            );
            $invoicesErrorCount = $invoicesRejectedCount + $invoicesTechnicalErrorCount;

            $tenantName = (string)($this->currentTenantName ?? '');
            $subject = sprintf('[SUNAT Daily Summary] %s - %s', $companyName, $today);
            $message = implode("\n", [
                sprintf('Resumen SUNAT diario (%s)', $today),
                'Tenant: ' . ($tenantName !== '' ? $tenantName : 'default'),
                'Empresa: ' . (string)$companyName,
                'Comprobantes emitidos: ' . $invoicesSentCount,
                'Aceptados: ' . $invoicesAcceptedCount,
                'Rechazados: ' . $invoicesRejectedCount,
                'Errores técnicos: ' . $invoicesTechnicalErrorCount,
                'Pendientes: ' . $invoicesPendingCount,
                'Anulados: ' . $cancellationsCount,
            ]);
            $payload = [
                'date' => $today,
                'tenant_name' => $tenantName !== '' ? $tenantName : 'default',
                'company_name' => (string)$companyName,
                'sales_count' => $salesCount,
                'new_customers_count' => $newCustomersCount,
                'recurring_customers_count' => $recurringCustomersCount,
                'total_sent' => $invoicesSentCount,
                'accepted' => $invoicesAcceptedCount,
                'rejected' => $invoicesRejectedCount,
                'technical_errors' => $invoicesTechnicalErrorCount,
                'pending' => $invoicesPendingCount,
                'cancellations' => $cancellationsCount,
                'errors_total' => $invoicesErrorCount,
            ];

            $dispatched = 0;
            if ($tenantName !== '') {
                $dispatched = $this->notificationService->dispatchForTenantName(
                    tenantName: $tenantName,
                    eventKey: TenantNotificationService::EVENT_SUNAT_DAILY_SUMMARY,
                    subject: $subject,
                    message: $message,
                    payload: $payload,
                    severity: 'info',
                );
            }

            if ($dispatched === 0) {
                $this->sendToBirdApi(
                    $companyName,
                    $salesCount,
                    $newCustomersCount,
                    $recurringCustomersCount,
                    $invoicesSentCount,
                    $cancellationsCount,
                    $invoicesErrorCount,
                    $io
                );
            }

            $io->success('Daily summary sent successfully');

            return Command::SUCCESS;
        } catch (Exception $exception) {
            $this->logger->error('Error sending daily summary', [
                'exception' => $exception,
            ]);
            $io->error('Error sending daily summary: ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Send the daily summary to Bird API
     */
    private function sendToBirdApi(
        string $companyName,
        int $salesCount,
        int $newCustomersCount,
        int $recurringCustomersCount,
        int $invoicesSentCount,
        int $cancellationsCount,
        int $invoicesErrorCount,
        SymfonyStyle $io
    ): void {
        $accessKey = $_ENV['BIRD_ACCESS_KEY'] ?? null;
        $phoneNumber = $_ENV['BIRD_PHONE_NUMBER'] ?? null;
        $workspaceId = $_ENV['BIRD_WORKSPACE_ID'] ?? '318c253a-2a11-4c05-863f-3c9bd4401c6e';
        $channelId = $_ENV['BIRD_CHANNEL_ID'] ?? 'f9f76ab7-9340-5b07-9841-56433f492b19';
        $projectId = $_ENV['BIRD_PROJECT_ID'] ?? 'b9eca45b-de1f-4d2d-94e1-5ec0810032a8';
        $versionId = $_ENV['BIRD_VERSION_ID'] ?? '91909c04-dd87-466c-a89c-8694dd0c979c';

        if (!$accessKey || !$phoneNumber) {
            $io->warning('BIRD_ACCESS_KEY or BIRD_PHONE_NUMBER environment variables are not set');

            return;
        }

        $httpClient = HttpClient::create();

        $dateString = date('d de MMMM de Y');

        $url = sprintf('https://api.bird.com/workspaces/%s/channels/%s/messages', $workspaceId, $channelId);

        $data = [
            'receiver' => [
                'contacts' => [
                    [
                        'identifierValue' => $phoneNumber,
                        'identifierKey' => 'phonenumber',
                    ],
                ],
            ],
            'template' => [
                'projectId' => $projectId,
                'version' => $versionId,
                'locale' => 'es',
                'parameters' => [
                    [
                        'type' => 'string',
                        'key' => '1',
                        'value' => $dateString,
                    ],
                    [
                        'type' => 'string',
                        'key' => '2',
                        'value' => $companyName,
                    ],
                    [
                        'type' => 'string',
                        'key' => '3',
                        'value' => (string)$salesCount,
                    ],
                    [
                        'type' => 'string',
                        'key' => '4',
                        'value' => (string)$newCustomersCount,
                    ],
                    [
                        'type' => 'string',
                        'key' => '5',
                        'value' => (string)$recurringCustomersCount,
                    ],
                    [
                        'type' => 'string',
                        'key' => '6',
                        'value' => (string)$invoicesSentCount,
                    ],
                    [
                        'type' => 'string',
                        'key' => '7',
                        'value' => (string)$cancellationsCount,
                    ],
                    [
                        'type' => 'string',
                        'key' => '8',
                        'value' => (string)$invoicesErrorCount,
                    ],
                ],
            ],
        ];

        try {
            $response = $httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'AccessKey ' . $accessKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent();

            $io->note('Bird API response: Status ' . $statusCode);
            $io->note('Response content: ' . $content);

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Daily summary sent successfully to Bird API', [
                    'status_code' => $statusCode,
                    'response' => $content,
                ]);
            } else {
                $this->logger->warning('Unexpected response from Bird API', [
                    'status_code' => $statusCode,
                    'response' => $content,
                ]);
            }
        } catch (
            ClientExceptionInterface |
            RedirectionExceptionInterface |
            ServerExceptionInterface |
            TransportExceptionInterface $e
        ) {
            $this->logger->error('Error sending request to Bird API', [
                'exception' => $e,
            ]);
            throw new RuntimeException('Error sending request to Bird API: ' . $e->getMessage(), 0, $e);
        }
    }
}
