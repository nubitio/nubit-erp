<?php

declare(strict_types=1);

namespace App\Integration\Fiscal;

use App\Billing\Sunat\Model\Response\DocumentResponse;

/**
 * Reads greenter's response shape into a {@see SunatOutcome}.
 *
 * SUNAT CDR code `0` is acceptance; `2000-3999` are permanent rejections;
 * `4000+` are observations (accepted with warnings). A response with no CDR
 * but a transport-level error is a rejection with that message.
 */
final class SunatOutcomeMapper
{
    public function from(DocumentResponse $response): SunatOutcome
    {
        $bill = $response->sunatResponse;
        $cdr = $bill->getCdrResponse();

        $code = $cdr?->getCode();
        $description = $cdr?->getDescription();
        $cdrId = $cdr?->getId();

        if ($bill->isSuccess() && (null === $code || '0' === (string) $code || (int) $code >= 4000)) {
            return new SunatOutcome(
                accepted: true,
                sunatStatus: null === $code ? '0' : (string) $code,
                cdrReference: is_string($cdrId) && '' !== $cdrId ? $cdrId : null,
                message: $description,
            );
        }

        $error = $bill->getError();
        $errorMessage = null !== $error ? (string) $error->getMessage() : null;
        $errorCode = null !== $error && null !== $error->getCode() ? (string) $error->getCode() : null;

        return new SunatOutcome(
            accepted: false,
            sunatStatus: null !== $code ? (string) $code : $errorCode,
            cdrReference: is_string($cdrId) && '' !== $cdrId ? $cdrId : null,
            message: $description ?? $errorMessage ?? 'SUNAT rejected the document',
        );
    }
}
