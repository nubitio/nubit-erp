<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\SaleRecordRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Parses, deduplicates and persists a single opening-balance row on behalf of
 * {@see OpeningReceivableImporter}. Split out so the orchestrating class only
 * owns the batch loop (tenant resolution, flush cadence, ledger booking).
 */
final readonly class OpeningReceivableRowImporter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CustomerRepository $customerRepository,
        private SaleRecordRepository $saleRecordRepository,
    ) {}

    /**
     * @param array<string, mixed> $raw
     */
    public function import(
        array $raw,
        OpeningReceivableTenantContext $tenant,
        int $userId,
        DateTimeImmutable $now,
    ): OpeningReceivableRowError|OpeningReceivableRowSkipped|OpeningReceivableChargeToRecord {
        $parsed = OpeningBalanceRow::tryFrom($raw);
        if (is_string($parsed)) {
            return new OpeningReceivableRowError($parsed);
        }

        $retryKey = $this->buildRetryKey($parsed);
        if ($this->saleRecordRepository->findByOfflineRetryKey($retryKey) !== null) {
            return new OpeningReceivableRowSkipped();
        }

        $customer = $this->getOrCreateCustomer($parsed);
        $sale = $this->buildSale($parsed, $customer, $tenant, $userId, $now, $retryKey);
        $this->entityManager->persist($sale);

        return new OpeningReceivableChargeToRecord(
            customer: $customer,
            saleId: (string) $sale->getId(),
            occurredOn: $sale->getIssuedDate() ?? $now,
            amount: $parsed->amount,
            description: $this->documentLabel($sale),
        );
    }

    /**
     * Deterministic key so re-uploading the same Excel is a safe no-op:
     * reuses `sales.offline_retry_key` (unique, already used by the offline
     * POS retry flow) instead of adding a new column.
     */
    private function buildRetryKey(OpeningBalanceRow $row): string
    {
        $seed = sprintf(
            'opening-balance:%s:%s:%s:%s',
            $row->ruc,
            $row->number ?? '',
            $row->amount,
            $row->dueDate->format('Y-m-d'),
        );
        $hex = md5($seed);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, offset: 0, length: 8),
            substr($hex, offset: 8, length: 4),
            substr($hex, offset: 12, length: 4),
            substr($hex, offset: 16, length: 4),
            substr($hex, offset: 20, length: 12),
        );
    }

    private function documentLabel(SaleRecord $sale): string
    {
        $serie = $sale->getSerie() ?? '';
        $numero = $sale->getNumero() ?? '';

        return $serie !== '' && $numero !== '' ? sprintf('%s-%s', $serie, $numero) : 'Saldo inicial importado';
    }

    private function getOrCreateCustomer(OpeningBalanceRow $row): Customer
    {
        $existing = $this->customerRepository->findOneBy(['ruc' => $row->ruc]);
        if ($existing instanceof Customer) {
            if ($row->phone !== null && $existing->getPhone() === null) {
                $existing->setPhone($row->phone);
            }

            return $existing;
        }

        $customer = new Customer()
            ->setRuc($row->ruc)
            ->setBusinessName($row->name)
            ->setPhone($row->phone)
            ->setIsActive(true);
        $this->entityManager->persist($customer);

        return $customer;
    }

    private function buildSale(
        OpeningBalanceRow $row,
        Customer $customer,
        OpeningReceivableTenantContext $tenant,
        int $userId,
        DateTimeImmutable $now,
        string $retryKey,
    ): SaleRecord {
        $issuedDate = ($row->issuedDate ?? $now)->setTime(0, 0);
        $issuedAt = DateTime::createFromImmutable($issuedDate);
        $number =
            $row->number !== null && $row->number !== ''
                ? $row->number
                : 'SI-' . substr(bin2hex(random_bytes(4)), offset: 0, length: 8);

        $sale = new SaleRecord()
            ->setWarehouse($tenant->warehouse)
            ->setAlmacen($tenant->warehouse)
            ->setBranch($tenant->branch)
            ->setCliente($customer)
            ->setFecha($issuedAt)
            ->setIssuedAt($issuedAt)
            ->setIssuedDate($issuedDate)
            ->setDueDate($row->dueDate)
            ->setFormaPago(2)
            ->setCreditSale(true)
            ->setTipoPago($tenant->paymentMethod)
            ->setHasInvoice(false)
            ->setEstado(1)
            ->setStatus('COMPLETED')
            ->setCurrency('PEN')
            ->setSubtotal($row->amount)
            ->setTaxTotal('0.00')
            ->setTotal($row->amount)
            ->setGrandTotal($row->amount)
            ->setPaidTotal('0.00')
            ->setBalanceDue($row->amount)
            ->setCreatedBy($userId)
            ->setSellerUserId($userId)
            ->setNote('Saldo inicial importado desde Excel. No es un comprobante SUNAT.')
            ->setInvoice($number)
            ->setSerie('SI')
            ->setNumero($number)
            ->setOfflineRetryKey($retryKey);

        $item = new SaleItemRecord()
            ->setLineNo(1)
            ->setQty('1.000')
            ->setUnitPrice($row->amount)
            ->setGrossAmount($row->amount)
            ->setTaxAmount('0.00')
            ->setNetAmount($row->amount)
            ->setDiscountAmount('0.00')
            ->setNameSnapshot('Saldo inicial')
            ->setSkuSnapshot($number);
        $sale->addSaleItemRecord($item);

        return $sale;
    }
}
