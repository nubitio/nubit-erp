<?php

declare(strict_types=1);

namespace App\Setup\Infrastructure\Billing;

use App\Billing\Sunat\Domain\BillRepositoryInterface;
use App\Billing\Sunat\Model\Response\DocumentResponse;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\CdrResponse;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Sale\Invoice;
use Override;

/**
 * Decorates {@see BillRepositoryInterface} when E2E seeding is enabled so Playwright
 * golden-path specs can exercise SUNAT send + CDR + PDF without calling Lycet.
 */
final readonly class E2eStubBillRepository implements BillRepositoryInterface
{
    private const string STUB_XML = '<?xml version="1.0" encoding="UTF-8"?><InvoiceE2EStub/>';

    private const string STUB_PDF = <<<'PDF'
        %PDF-1.4
        1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
        2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj
        3 0 obj<</Type/Page/MediaBox[0 0 300 144]/Parent 2 0 R>>endobj
        xref
        0 4
        0000000000 65535 f
        0000000009 00000 n
        0000000052 00000 n
        0000000101 00000 n
        trailer<</Size 4/Root 1 0 R>>
        startxref
        178
        %%EOF
        PDF;

    public function __construct(
        private BillRepositoryInterface $inner,
    ) {}

    #[Override]
    public function sendBill(Invoice $invoice): DocumentResponse
    {
        if (!$this->isStubEnabled()) {
            return $this->inner->sendBill($invoice);
        }

        $name = $this->resolveDocumentName($invoice);
        $cdrResponse = new CdrResponse()
            ->setCode('0')
            ->setDescription('ACEPTADO - Comprobante aceptado (E2E stub)')
            ->setId($name);

        $billResult = new BillResult()
            ->setSuccess(true)
            ->setCdrResponse($cdrResponse)
            ->setCdrZip(base64_encode('PK e2e-stub-cdr'));

        $response = new DocumentResponse();
        $response->name = $name;
        $response->xml = self::STUB_XML;
        $response->hash = hash('sha256', self::STUB_XML);
        $response->sunatResponse = $billResult;

        return $response;
    }

    #[Override]
    public function getXml(Invoice $invoice): string
    {
        if (!$this->isStubEnabled()) {
            return $this->inner->getXml($invoice);
        }

        return self::STUB_XML;
    }

    #[Override]
    public function getPdf(Invoice $invoice): string
    {
        if (!$this->isStubEnabled()) {
            return $this->inner->getPdf($invoice);
        }

        return self::STUB_PDF;
    }

    #[Override]
    public function getStatus(Invoice $invoice): StatusCdrResult
    {
        if (!$this->isStubEnabled()) {
            return $this->inner->getStatus($invoice);
        }

        $name = $this->resolveDocumentName($invoice);

        return new StatusCdrResult()
            ->setSuccess(true)
            ->setCdrResponse(
                new CdrResponse()
                    ->setCode('0')
                    ->setDescription('ACEPTADO - Comprobante aceptado (E2E stub)')
                    ->setId($name),
            )
            ->setCdrZip(base64_encode('PK e2e-stub-cdr'));
    }

    private function isStubEnabled(): bool
    {
        if (array_key_exists('E2E_SEED_ENABLED', $_SERVER)) {
            return $_SERVER['E2E_SEED_ENABLED'] === '1';
        }

        $flag = getenv('E2E_SEED_ENABLED');

        return $flag === '1';
    }

    private function resolveDocumentName(Invoice $invoice): string
    {
        $series = trim($invoice->getSerie() ?? 'B001');
        $number = trim($invoice->getCorrelativo() ?? '1');

        return $series . '-' . $number;
    }
}
