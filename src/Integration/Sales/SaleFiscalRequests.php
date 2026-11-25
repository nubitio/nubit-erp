<?php

declare(strict_types=1);

namespace App\Integration\Sales;

use App\Billing\Invoice\Domain\InvoiceSendRetryService;
use App\Billing\Service\InvoiceCreationServiceInterface;
use App\Billing\Service\SaleV2Loader;
use App\Efact\Domain\Money;
use App\Integration\Security\ApiClientUser;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class SaleFiscalRequests
{
    public function __construct(
        private Connection $connection,
        private SalesClientAccess $access,
        private SaleV2Loader $sales,
        private InvoiceCreationServiceInterface $invoices,
        private InvoiceSendRetryService $retry,
    ) {}

    /** @return array{sale_id: string, document_id: string, status: string}|null */
    public function find(ApiClientUser $client, string $saleId): ?array
    {
        $this->requireSale($client, $saleId);

        return $this->result($saleId);
    }

    /** @return array{sale_id: string, document_id: string, status: string} */
    public function request(ApiClientUser $client, string $saleId): array
    {
        $this->requireSale($client, $saleId);

        return $this->connection->transactional(function () use ($client, $saleId): array {
            $sale = $this->connection->fetchAssociative(<<<'SQL'
                SELECT s.status, s.balance_due, s.paid_total
                FROM sales s
                JOIN integration_api_client c ON c.id = s.integration_client_id AND c.sales_branch_id = s.branch_id
                JOIN local b ON b.local_id = c.sales_branch_id AND b.is_active = 1
                WHERE s.id = ? AND c.id = ? AND s.deleted_at IS NULL
                FOR UPDATE
                SQL, [$saleId, $client->getClientId()]);
            if ($sale === false) {
                throw new NotFoundHttpException('Sale not found.');
            }

            $existing = $this->result($saleId);
            if ($existing !== null) {
                return $existing;
            }
            if (
                $sale['status'] !== 'COMPLETED'
                || !Money::money($sale['balance_due'])->isZero()
                || !Money::money($sale['paid_total'])->isPositive()
            ) {
                throw new UnprocessableEntityHttpException('Only a fully paid sale can request a document.');
            }

            return $this->create($saleId);
        });
    }

    private function requireSale(ApiClientUser $client, string $saleId): void
    {
        if ($this->access->findSale($client, $saleId) === null) {
            throw new NotFoundHttpException('Sale not found.');
        }
    }

    /** @return array{sale_id: string, document_id: string, status: string}|null */
    private function result(string $saleId): ?array
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
            SELECT r.document_id, d.sunat_status, d.cdr_path, d.issue_date < DATE_SUB(CURRENT_DATE(), INTERVAL 3 DAY) AS expired
            FROM integration_sale_document r JOIN tax_document d ON d.tax_document_id = r.document_id
            WHERE r.sale_id = ?
            SQL, [$saleId]);
        if ($row === false) {
            return null;
        }

        $id = (string) $row['document_id'];
        $code = (string) $row['sunat_status'];
        $status = match (true) {
            $code === '0', ctype_digit($code) && (int) $code >= 4000 && $row['cdr_path'] !== null => 'accepted',
            ctype_digit($code) && (int) $code >= 2000 && (int) $code <= 3999 => 'rejected',
            (bool) $row['expired'] => 'failed',
            default => $this->retry->evaluateRetryEligibility($id, $this->retry->getRetryPolicy(null))['reason']
                === 'max_attempts'
                    ? 'failed'
                    : 'queued',
        };

        return ['sale_id' => $saleId, 'document_id' => $id, 'status' => $status];
    }

    /** @return array{sale_id: string, document_id: string, status: string} */
    private function create(string $saleId): array
    {
        $sale = $this->sales->loadAsSale($saleId) ?? throw new NotFoundHttpException('Sale not found.');
        $invoice = $this->invoices->createForSale($sale);
        $documentId = $invoice['invoiceId'] ?? null;
        if (!$invoice['success'] || $documentId === null) {
            throw new UnprocessableEntityHttpException($invoice['message']);
        }

        $this->connection->insert('integration_sale_document', [
            'sale_id' => $saleId,
            'document_id' => $documentId,
        ]);

        return ['sale_id' => $saleId, 'document_id' => $documentId, 'status' => 'queued'];
    }
}
