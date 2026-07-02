<?php

declare(strict_types=1);

namespace App\Purchasing\Service;

use App\Purchasing\Entity\Purchase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PurchaseSupplierDocumentValidator
{
    public function assertRequiredForCreate(Purchase $purchase): void
    {
        $docType = trim((string) ($purchase->getSupplierDocType() ?? ''));
        $series = trim((string) ($purchase->getSupplierDocSeries() ?? ''));
        $number = trim((string) ($purchase->getSupplierDocNumber() ?? ''));

        if ($docType === '') {
            throw new UnprocessableEntityHttpException('El tipo de comprobante del proveedor es obligatorio.');
        }

        if ($series === '') {
            throw new UnprocessableEntityHttpException('La serie del comprobante del proveedor es obligatoria.');
        }

        if ($number === '') {
            throw new UnprocessableEntityHttpException('El número del comprobante del proveedor es obligatorio.');
        }
    }
}
