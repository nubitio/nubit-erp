<?php

declare(strict_types=1);

namespace App\Billing\Repository;

use App\Catalog\Entity\PaymentMethod;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\ServiceException;
use App\Core\Util\Money;
use App\Dto\Invoice\VoucherHeaderDto;
use App\Dto\Invoice\VoucherLineDto;
use App\Dto\TaxDocument\SeriesResultDto;
use App\EInvoicing\Invoice\Domain\Voucher;
use App\EInvoicing\Invoice\Domain\VoucherItem;
use App\EInvoicing\Service\InvoiceAmounts;
use App\EInvoicing\Service\InvoiceCalculator;
use App\EInvoicing\Service\InvoiceLineAmounts;
use App\EInvoicing\Service\InvoiceLineInput;
use App\EInvoicing\Service\SaleV2Loader;
use App\Organization\Entity\Branch;
use App\Sales\Entity\SaleRecord;
use App\Catalog\Entity\SunatCatalog;
use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use App\Catalog\Entity\VoucherSeries;
use App\Sales\Domain\DocType;
use Brick\Math\Exception\MathException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxDocument>
 */
class TaxDocumentRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SaleV2Loader $saleV2Loader,
    ) {
        parent::__construct($registry, TaxDocument::class);
    }

    /**
     * @throws ORMException
     */
    public function getTaxDocumentSeriesByBranch(string $branchId, string $documentType, ?string $associatedSeries = null): ?SeriesResultDto
    {
        $branchRef = $this->getEntityManager()->getReference(Branch::class, $branchId);
        $qb = $this->getEntityManager()
            ->getRepository(VoucherSeries::class)
            ->createQueryBuilder('vs')
            ->leftJoin('vs.voucherType', 'vt')
            ->where('vs.branch = :branch')
            ->andWhere('vt.code = :documentType')
            ->setParameter('branch', $branchRef)
            ->setParameter('documentType', $documentType);

        if ($associatedSeries !== null) {
            $qb->andWhere('vs.associatedSeries = :associatedSeries')
               ->setParameter('associatedSeries', $associatedSeries);
        }

        $result = $qb->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$result instanceof VoucherSeries) {
            return null;
        }

        return new SeriesResultDto(
            series: $result->getSeries(),
            associatedSeries: $result->getAssociatedSeries()
        );
    }

    /**
     * @throws ORMException
     */
    public function hasCreditNoteForSale(string $saleId): bool
    {
        $legacySaleId = $this->resolveLegacySaleIdForVoucher($saleId);
        $count = $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
SELECT COUNT(*)
FROM tax_document td
WHERE td.movimiento_id = :sale_id
  AND td.document_type = :doc_type
SQL,
            [
                'sale_id' => $legacySaleId,
                'doc_type' => '07',
            ]
        );

        return (int) ($count !== false ? $count : 0) > 0;
    }

    /**
     * @throws ORMException
     */
    public function getNextSequenceNumber(string $branchId, string $documentType): int
    {
        $branchRef = $this->getEntityManager()->getReference(Branch::class, $branchId);
        $numbers = $this->createQueryBuilder('td')
            ->select('td.number')
            ->where('td.branch = :branch')
            ->andWhere('td.documentType = :docType')
            ->setParameter('branch', $branchRef)
            ->setParameter('docType', $documentType)
            ->getQuery()
            ->getArrayResult();

        $max = 0;
        foreach ($numbers as $row) {
            $value = ltrim((string)($row['number'] ?? ''), '0');
            $value = $value === '' ? '0' : $value;
            $numeric = (int)$value;
            if ($numeric > $max) {
                $max = $numeric;
            }
        }

        return $max + 1;
    }

    /**
     * @throws MathException
     */
    public function getVoucher(string $saleId): Voucher
    {
        $resolvedSaleId = $this->resolveLegacySaleIdForVoucher($saleId);
        $sale = $this->loadSaleGraph($resolvedSaleId);

        if (!$sale) {
            throw NotFoundException::forResource('Sale', $saleId);
        }

        // Load the active tax document for this sale explicitly by sale id.
        $taxDocument = $this->findActiveTaxDocumentBySaleId($resolvedSaleId)
            ?? $this->findActiveTaxDocumentBySaleId($saleId);
        $header = $this->buildHeaderFromSale($sale, $taxDocument, null);
        $lines = $this->buildLinesForVoucher($sale, $taxDocument);

        // Nota de venta: preserve persisted line totals
        if ($header->voucherType === DocType::NOTA_VENTA->value) {
            $this->applyNotaVentaTotals($sale, $header);

            $voucherItems = array_map(
                static fn (VoucherLineDto $line): VoucherItem => $line->toVoucherItem(),
                $lines
            );

            return $header->toVoucher($voucherItems);
        }

        $amounts = $this->calculateAmounts(
            $lines,
            $header->voucherDiscountPercentage ?? '0',
            $header->voucherTaxPercentage ?? '0',
            $header->voucherConsumptionSurchargePercentage ?? '0',
            $taxDocument?->getTotal() ?? $sale->getTotal()
        );

        $this->applyAmountsToLines($lines, $amounts);
        $header->applyAmounts($amounts);
        $header->voucherQrData = $this->buildQrData($sale, $taxDocument, $amounts);

        $voucherItems = array_map(
            static fn (VoucherLineDto $line): VoucherItem => $line->toVoucherItem(),
            $lines
        );

        return $header->toVoucher($voucherItems);
    }

    /**
     * @throws MathException
     */
    public function getVoucherByTaxDocumentId(string $taxDocumentId): Voucher
    {
        $saleId = $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
SELECT movimiento_id
FROM tax_document
WHERE tax_document_id = :tax_document_id
LIMIT 1
SQL,
            ['tax_document_id' => $taxDocumentId]
        );

        if (!is_string($saleId) || $saleId === '') {
            throw new ServiceException('El comprobante no existe');
        }

        return $this->getVoucher($saleId);
    }

    /**
     * Returns the document_type (e.g. '01', '03', 'NV') for any active invoice
     * linked to the given sale, searching by both movimiento_id and sale_id.
     * Returns null when no tax_document exists for the sale.
     */
    public function findDocumentTypeBySaleId(string $saleId): ?string
    {
        $resolvedId = $this->resolveLegacySaleIdForVoucher($saleId);
        $type = $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
SELECT document_type
FROM tax_document
WHERE (movimiento_id = :sale_id OR sale_id = :sale_id)
  AND document_type NOT IN ('07', '08')
ORDER BY created_at DESC
LIMIT 1
SQL,
            ['sale_id' => $resolvedId]
        );

        if (!is_string($type) || $type === '') {
            // Also try the original (V2) id in case resolveLegacySaleIdForVoucher
            // returned the legacy id and the tax_document only has the V2 id.
            if ($resolvedId !== $saleId) {
                $type = $this->getEntityManager()->getConnection()->fetchOne(
                    <<<'SQL'
SELECT document_type
FROM tax_document
WHERE (movimiento_id = :sale_id OR sale_id = :sale_id)
  AND document_type NOT IN ('07', '08')
ORDER BY created_at DESC
LIMIT 1
SQL,
                    ['sale_id' => $saleId]
                );
            }
        }

        return is_string($type) && $type !== '' ? $type : null;
    }

    private function findActiveTaxDocumentBySaleId(string $saleId): ?TaxDocument
    {
        $taxDocId = $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
SELECT tax_document_id
FROM tax_document
WHERE (movimiento_id = :id OR sale_id = :id)
  AND reason_code IS NULL
ORDER BY created_at DESC
LIMIT 1
SQL,
            ['id' => $saleId]
        );

        if (!is_string($taxDocId) || $taxDocId === '') {
            return null;
        }

        return $this->createQueryBuilder('td')
            ->leftJoin('td.lines', 'tdl')->addSelect('tdl')
            ->where('td.id = :id')
            ->setParameter('id', $taxDocId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function resolveLegacySaleIdForVoucher(string $saleId): string
    {
        $connection = $this->getEntityManager()->getConnection();
        $legacyId = $connection->fetchOne(
            <<<'SQL'
SELECT s.legacy_movimiento_id
FROM sales s
WHERE s.id = :sale_id
   OR s.legacy_movimiento_id = :sale_id
ORDER BY CASE WHEN s.legacy_movimiento_id = :sale_id THEN 0 ELSE 1 END
LIMIT 1
SQL,
            ['sale_id' => $saleId]
        );

        if (is_string($legacyId) && $legacyId !== '') {
            return $legacyId;
        }

        return $saleId;
    }

    /**
     * @throws MathException
     */
    public function getNoteVoucher(string $taxDocumentId): Voucher
    {
        $creditNote = $this->createQueryBuilder('td')
            ->leftJoin('td.relatedTaxDocument', 'rtd')->addSelect('rtd')
            ->leftJoin('td.lines', 'tdl')->addSelect('tdl')
            ->where('td.id = :taxDocumentId')
            ->setParameter('taxDocumentId', $taxDocumentId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$creditNote) {
            throw new ServiceException('La nota de crédito no existe');
        }

        $rawSaleId = $creditNote->getCanonicalSaleId();

        if ($rawSaleId === null) {
            throw new ServiceException('La nota no tiene una venta asociada');
        }

        $resolvedSaleId = $this->resolveLegacySaleIdForVoucher($rawSaleId);
        $sale = $this->loadSaleGraph($resolvedSaleId);
        if (!$sale) {
            throw new ServiceException('La venta asociada no existe');
        }
        $relatedTaxDocument = $creditNote->getRelatedTaxDocument();
        $header = $this->buildHeaderFromSale($sale, $creditNote, $relatedTaxDocument);
        $lines = $this->buildLinesForVoucher($sale, $creditNote);

        $amounts = $this->calculateAmounts(
            $lines,
            $header->voucherDiscountPercentage ?? '0',
            $header->voucherTaxPercentage ?? '0',
            $header->voucherConsumptionSurchargePercentage ?? '0',
            $creditNote->getTotal()
        );

        $this->applyAmountsToLines($lines, $amounts);
        $header->applyAmounts($amounts);

        $voucherItems = array_map(
            static fn (VoucherLineDto $line): VoucherItem => $line->toVoucherItem(),
            $lines
        );

        return $header->toVoucher($voucherItems);
    }

    private function loadSaleGraph(string $saleId): ?SaleRecord
    {
        return $this->saleV2Loader->loadAsSale($saleId);
    }

    private function buildHeaderFromSale(
        SaleRecord $sale,
        ?TaxDocument $taxDocument,
        ?TaxDocument $relatedTaxDocument,
    ): VoucherHeaderDto {
        $customer = $sale->getCliente();
        $branch = $sale->getBranch() ?? $sale->getAlmacen()?->getBranch();
        $company = $branch?->getCompany();
        $customerRuc = $customer?->getRuc();
        $docType = $relatedTaxDocument?->getDocumentType()
            ?? $taxDocument?->getDocumentType()
            ?? DocType::NOTA_VENTA->value;

        $voucherSeries = $relatedTaxDocument?->getSeries()
            ?? $taxDocument?->getSeries()
            ?? $sale->getSerie();
        $voucherNumber = $relatedTaxDocument?->getNumber()
            ?? $taxDocument?->getNumber()
            ?? $sale->getNumero();

        $detractionCode = $taxDocument?->getDetractionCode() ?? $sale->getDetraccionCodigo();
        $detractionPaymentMethod = $taxDocument?->getDetractionPaymentMethod() ?? $sale->getDetraccionMedioPago();
        $detractionDescription = $this->findSunatCatalogDescription(54, $detractionCode);
        $detractionPaymentDescription = $this->findSunatCatalogDescription(59, $detractionPaymentMethod);
        $salesCurrencyData = $this->findSaleCurrencyData($sale->getId());

        return new VoucherHeaderDto(
            saleId: $sale->getId(),
            customerDocType: $customerRuc && strlen($customerRuc) === 11 ? '6' : '1',
            customerDocNumber: $customerRuc,
            customerName: $customer?->getBusinessName(),
            customerAddress: $customer?->getFiscalAddress(),
            customerEmail: $customer?->getEmail(),
            customerPhone: $customer?->getPhone(),
            companyId: $company?->getRuc(),
            companyName: $company?->getBusinessName(),
            companyAddress: $company?->getFiscalAddress(),
            companyPhone: $company?->getPhone(),
            branchAddress: $branch?->getAddress(),
            branchPhone: $branch?->getPhone(),
            voucherType: $docType,
            voucherSeries: $voucherSeries,
            voucherNumber: $voucherNumber,
            voucherPaymentMethod: $taxDocument?->getPaymentMethod() ?? $sale->getFormaPago(),
            voucherDate: (static function (?\DateTimeInterface $dt): ?string {
                if ($dt === null) {
                    return null;
                }
                $utcZone = new \DateTimeZone('UTC');
                $utcDate = \DateTimeImmutable::createFromInterface($dt)->setTimezone($utcZone);
                return $utcDate->format('Y-m-d H:i:s');
            })($taxDocument?->getIssueDate() ?? $sale->getFecha()),
            voucherCurrency: $taxDocument?->getCurrency() ?? $salesCurrencyData['currency'] ?? 'PEN',
            voucherExchangeRate: $taxDocument?->getExchangeRate() ?? $salesCurrencyData['exchange_rate'],
            voucherDiscountAmount: $taxDocument?->getDiscountAmount(),
            voucherDiscountPercentage: (string)($taxDocument?->getDiscountPercent() ?? $sale->getDescuento() ?? 0),
            voucherConsumptionSurchargeAmount: $taxDocument?->getRcAmount(),
            voucherConsumptionSurchargePercentage: (string)($sale->getRecargoConsumo() ?? 0),
            voucherTaxedAmount: $taxDocument?->getTaxableAmount(),
            voucherTaxAmount: $taxDocument?->getIgvAmount(),
            voucherTaxPercentage: (string)($sale->getIgv() ?? 0),
            voucherTotalTaxes: $taxDocument?->getIgvAmount(),
            voucherSaleValue: $taxDocument?->getSubtotal(),
            voucherSaleAmount: $taxDocument?->getTotal() ?? $sale->getTotal(),
            voucherOperationType: $taxDocument?->getOperationType() ?? $sale->getTipoOperacion(),
            voucherDetractionCode: $detractionCode,
            voucherDetractionPercent: $this->toNullableString(
                $taxDocument?->getDetractionPercent() ?? $sale->getDetraccionPorcentaje()
            ),
            voucherDetractionAmount: $this->toNullableString(
                $taxDocument?->getDetractionAmount() ?? $sale->getDetraccionMonto()
            ),
            voucherDetractionPaymentMethod: $detractionPaymentMethod,
            voucherDetractionPaymentDescription: $detractionPaymentDescription,
            voucherDetractionValueRef: $this->toNullableString(
                $taxDocument?->getDetractionValueRef() ?? $sale->getDetraccionValorReferencial()
            ),
            voucherDetractionAccount: $company?->getDetraccionAccount(),
            voucherDetractionDescription: $detractionDescription,
            voucherBankName: $company?->getBankName(),
            voucherBankAccount: $company?->getBankAccount(),
            voucherBankCci: $company?->getBankCci(),
            voucherNote: $sale->getGlosa(),
            voucherHash: $taxDocument?->getDigest(),
            voucherQrData: null,
            voucherStatus: $sale->getEstado(),
        );
    }

    /**
     * @return array{currency: ?string, exchange_rate: ?string}
     */
    public function getSalesCurrencyForSaleId(?string $saleId): array
    {
        return $this->findSaleCurrencyData($saleId);
    }

    /**
     * @return VoucherLineDto[]
     */
    private function buildLinesForVoucher(SaleRecord $sale, ?TaxDocument $taxDocument): array
    {
        if ($taxDocument && !$taxDocument->getLines()->isEmpty()) {
            return array_map(
                static fn (TaxDocumentLine $line): VoucherLineDto => new VoucherLineDto(
                    productId: $line->getProductId(),
                    productCode: $line->getProductCode(),
                    productUom: $line->getProductUom(),
                    productName: $line->getProductName(),
                    quantity: $line->getQuantity(),
                    unitValue: $line->getUnitValue(),
                    unitPrice: $line->getUnitPrice(),
                    discountAmount: $line->getDiscountAmount(),
                    taxedAmount: $line->getTaxedAmount(),
                    taxAmount: $line->getTaxAmount(),
                    taxPercentage: $line->getTaxPercentage(),
                    taxType: $line->getTaxType(),
                    total: $line->getTotal(),
                ),
                $taxDocument->getLines()->toArray()
            );
        }

        return array_map(
            fn ($item): VoucherLineDto => new VoucherLineDto(
                productId: $item->getProducto()?->getId(),
                productCode: $item->getProducto()?->getCode(),
                productUom: $item->getProducto()?->getUnitOfMeasure()?->getCode(),
                productName: $item->getProducto()?->getName() ?? '-',
                quantity: (string)$item->getCantidad(),
                unitPrice: $item->getPrecioUnitario() ?? '0',
                taxPercentage: (string)($sale->getIgv() ?? 0),
                taxType: '10',
                total: $item->getPrecioTotal() ?? '0',
            ),
            $sale->getItems()->toArray()
        );
    }

    /**
     * @return array{currency: ?string, exchange_rate: ?string}
     */
    private function findSaleCurrencyData(?string $saleId): array
    {
        if ($saleId === null || trim($saleId) === '') {
            return ['currency' => null, 'exchange_rate' => null];
        }

        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            <<<'SQL'
SELECT s.currency, s.exchange_rate
FROM sales s
WHERE s.id = :id OR s.legacy_movimiento_id = :id
ORDER BY CASE WHEN s.id = :id THEN 0 ELSE 1 END
LIMIT 1
SQL,
            ['id' => $saleId]
        );

        if (!is_array($row)) {
            return ['currency' => null, 'exchange_rate' => null];
        }

        return [
            'currency' => isset($row['currency']) ? (string)$row['currency'] : null,
            'exchange_rate' => isset($row['exchange_rate']) ? (string)$row['exchange_rate'] : null,
        ];
    }

    /**
     * @param array<int, VoucherLineDto> $lines
     * @throws MathException
     */
    private function calculateAmounts(
        array $lines,
        string $discountPercentage,
        string $taxPercentage,
        string $chargesPercentage,
        ?string $expectedTotal
    ): InvoiceAmounts {
        $calculator = new InvoiceCalculator(
            array_map(
                static fn (VoucherLineDto $line): InvoiceLineInput => new InvoiceLineInput(
                    quantity: $line->quantity ?? '0',
                    unitPrice: $line->unitPrice ?? '0'
                ),
                $lines
            ),
            $discountPercentage,
            $taxPercentage,
            $chargesPercentage,
            $expectedTotal
        );

        return $calculator->calculate();
    }

    /**
     * @param VoucherLineDto[] $lines
     */
    private function applyAmountsToLines(array $lines, InvoiceAmounts $amounts): void
    {
        $amountLines = $amounts->getLines();

        foreach ($lines as $index => $line) {
            $amountLine = $amountLines[$index] ?? null;
            if ($amountLine instanceof InvoiceLineAmounts) {
                $line->applyAmounts($amountLine);
            }
        }
    }

    /**
     * @throws MathException
     */
    private function applyNotaVentaTotals(SaleRecord $sale, VoucherHeaderDto $header): void
    {
        $subtotal = Money::money($sale->getSubtotal());
        $discountRate = Money::rate($sale->getDescuento() ?? 0);
        $discountAmount = Money::money($subtotal->multipliedBy($discountRate));
        $total = Money::money($sale->getTotal());

        $header->voucherDiscountAmount = Money::toString($discountAmount);
        $header->voucherTaxedAmount = '0.00';
        $header->voucherTaxAmount = '0.00';
        $header->voucherTotalTaxes = '0.00';
        $header->voucherConsumptionSurchargeAmount = '0.00';
        $header->voucherSaleValue = Money::toString($subtotal);
        $header->voucherSaleAmount = Money::toString($total);
    }

    private function findSunatCatalogDescription(int $catalog, ?string $code): ?string
    {
        if (!$code) {
            return null;
        }

        $catalogRow = $this->getEntityManager()
            ->getRepository(SunatCatalog::class)
            ->findOneBy(['catalog' => $catalog, 'code' => $code]);

        return $catalogRow?->getDescription();
    }

    private function buildQrData(
        SaleRecord $sale,
        ?TaxDocument $taxDocument,
        InvoiceAmounts $amounts
    ): ?string {
        if (!$taxDocument) {
            return null;
        }

        $customerRuc = $sale->getCliente()?->getRuc() ?? '';

        return implode('|', [
            $customerRuc,
            $taxDocument->getDocumentType(),
            $taxDocument->getSeries(),
            $taxDocument->getNumber(),
            $amounts->getTax(),
            $amounts->getTotal(),
            $taxDocument->getIssueDate()->format('Y-m-d H:i:s'),
            '0',
            '0',
            $taxDocument->getDigest() ?? '',
        ]);
    }

    private function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string)$value;
    }

    /**
     * @return array<string, mixed>|null
     * @throws \DateMalformedStringException
     */
    public function findTaxDocumentBySearchCriteria(
        string $companyRuc,
        string $voucherType,
        string $series,
        string $number,
        string $date,
        string $total,
    ): ?array {
        $dateStart = new \DateTimeImmutable($date . ' 00:00:00')->format('Y-m-d H:i:s');
        $dateEnd = new \DateTimeImmutable($date . ' 23:59:59')->format('Y-m-d H:i:s');
        $normalizedNumber = ltrim($number, '0');
        $normalizedNumber = $normalizedNumber === '' ? '0' : $normalizedNumber;
        $rows = $this->createQueryBuilder('td')
            ->leftJoin('td.branch', 'b')->addSelect('b')
            ->leftJoin('b.company', 'e')->addSelect('e')
            ->where('e.ruc = :companyRuc')
            ->andWhere('td.documentType = :voucherType')
            ->andWhere('td.series = :series')
            ->andWhere('td.issueDate BETWEEN :dateStart AND :dateEnd')
            ->andWhere('td.total = :total')
            ->setParameter('companyRuc', $companyRuc)
            ->setParameter('voucherType', $voucherType)
            ->setParameter('series', strtoupper($series))
            ->setParameter('dateStart', new \DateTimeImmutable($dateStart))
            ->setParameter('dateEnd', new \DateTimeImmutable($dateEnd))
            ->setParameter('total', number_format((float)$total, 2, '.', ''))
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            if (!$row instanceof TaxDocument) {
                continue;
            }
            $docNumber = ltrim($row->getNumber(), '0');
            $docNumber = $docNumber === '' ? '0' : $docNumber;
            if ($docNumber !== $normalizedNumber) {
                continue;
            }

            // In v2_authoritative mode the canonical sale ID comes from saleRecord (sales table).
            $legacySaleId = $row->getCanonicalSaleId();
            $saleStatusV2 = null;
            $saleStatusLegacy = null;

            if ($legacySaleId !== null && $legacySaleId !== '') {
                $saleRow = $this->getEntityManager()->getConnection()->fetchAssociative(
                    'SELECT status FROM sales WHERE legacy_movimiento_id = :id OR id = :id LIMIT 1',
                    ['id' => $legacySaleId]
                );
                if (is_array($saleRow)) {
                    $saleStatusV2 = isset($saleRow['status']) ? (string) $saleRow['status'] : null;
                }
            }

            return [
                'tax_document_id' => $row->getId(),
                'movimiento_id' => $legacySaleId,
                'document_type' => $row->getDocumentType(),
                'series' => $row->getSeries(),
                'number' => $row->getNumber(),
                'issue_date' => $row->getIssueDate()->format('Y-m-d H:i:s'),
                'total' => $row->getTotal(),
                'digest' => $row->getDigest(),
                'sunat_status' => $row->getSunatStatus(),
                'sale_status' => $saleStatusLegacy,
                'sale_status_v2' => $saleStatusV2,
                'currency' => $row->getCurrency() ?? 'PEN',
            ];
        }

        return null;
    }
}
