<?php

declare(strict_types=1);

namespace App\Billing\Consult\Infrastructure;

use App\Billing\Consult\Domain\DocumentInfo;
use App\Billing\Consult\Domain\LookupException;
use Psr\Log\LoggerInterface;

/**
 * Retrieves DNI information by constructing the natural-person RUC and
 * querying SUNAT — no external token required.
 *
 * Every Peruvian natural person can be registered as a taxpayer with:
 *   RUC = "10" + DNI (8 digits) + verification_digit
 *
 * If the person has an active RUC, SUNAT returns their full name via the
 * same 3-step scraping flow used for companies (SunatRucScraper).
 *
 * Limitation: people who have never registered with SUNAT won't be found.
 * In that case the caller receives a LookupException::notFound.
 *
 * Ported from https://github.com/giansalex/peru-consult (MIT).
 */
final class SunatDniScraper
{
    public function __construct(
        private readonly SunatRucScraper $rucScraper,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws LookupException
     */
    public function lookup(string $dni): DocumentInfo
    {
        if (8 !== strlen($dni) || !ctype_digit($dni)) {
            throw LookupException::notFound('DNI', $dni);
        }

        $ruc = $this->buildNaturalPersonRuc($dni);

        $this->logger->debug('SunatDniScraper: querying SUNAT via natural-person RUC', [
            'dni' => $dni,
            'ruc' => $ruc,
        ]);

        try {
            $info = $this->rucScraper->lookup($ruc);
        } catch (LookupException) {
            throw LookupException::notFound('DNI', $dni);
        }

        return new DocumentInfo(
            id: $dni,
            name: $info->name,
            address: '', // don't expose fiscal address for natural persons
            active: true,
        );
    }

    /**
     * Constructs the 11-digit natural-person RUC from an 8-digit DNI.
     *
     * Formula  : "10" + DNI + check_digit
     * Weights  : [5, 4, 3, 2, 7, 6, 5, 4, 3, 2] on the 10-digit prefix
     * check    = 11 - (Σ(digit × weight) mod 11)
     *            → 1  when result is 11
     *            → 0  when result is 10
     */
    private function buildNaturalPersonRuc(string $dni): string
    {
        $prefix = '10' . $dni;
        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 10; ++$i) {
            $sum += (int) $prefix[$i] * $weights[$i];
        }

        $check = 11 - ($sum % 11);
        if (11 === $check) {
            $check = 1;
        }
        if (10 === $check) {
            $check = 0;
        }

        return $prefix . $check;
    }
}
