<?php

declare(strict_types=1);

namespace App\Sales\Port;

use App\Sales\Port\Result\RejectedDocumentReplacementResult;

/**
 * Replaces a rejected electronic document with a freshly issued one.
 */
interface RejectedDocumentReplacerInterface
{
    public function replaceRejectedDocument(string $saleId): RejectedDocumentReplacementResult;
}
