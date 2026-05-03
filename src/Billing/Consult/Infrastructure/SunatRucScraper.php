<?php

declare(strict_types=1);

namespace App\Billing\Consult\Infrastructure;

use App\Billing\Consult\Domain\DocumentInfo;
use App\Billing\Consult\Domain\LookupException;
use Psr\Log\LoggerInterface;

/**
 * Retrieves RUC information by scraping SUNAT's public consultation page.
 *
 * This is a PHP port of https://github.com/giansalex/peru-consult (MIT).
 *
 * The 3-step flow (required — SUNAT won't return data without it):
 *   1. GET  /jcrS00Alias           → establishes a session cookie
 *   2. POST accion=consPorRazonSoc → gets an HTML page that contains
 *                                    a hidden <input name="numRnd"> token
 *   3. POST accion=consPorRuc      → returns the actual company HTML
 *
 * All three requests use the same cURL handle so that the session cookie
 * (ITMRCONSRUCSESSION) is automatically forwarded. Without the numRnd
 * token from step 2, SUNAT returns an empty 200 response.
 *
 * HTML parsing mirrors peru-consult's HtmlRecaptchaParser:
 *   - Finds the first <div class="list-group"> wrapper
 *   - Extracts .list-group-item-heading → .list-group-item-text pairs
 */
final class SunatRucScraper
{
    private const string CONSULT_URL = 'https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/jcrS00Alias';

    /** Seed query used only to obtain the numRnd token (arbitrary company name). */
    private const string SEED_RAZON_SOC = 'BVA FOODS';

    private const string USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) '
        . 'Chrome/124.0.0.0 Safari/537.36';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws LookupException
     */
    public function lookup(string $ruc): DocumentInfo
    {
        $ch = $this->createCurlHandle();

        try {
            // Step 1: establish session
            $this->curlGet($ch, self::CONSULT_URL);

            // Step 2: seed search → extract numRnd
            $seedHtml = $this->curlPost($ch, self::CONSULT_URL, [
                'accion' => 'consPorRazonSoc',
                'razSoc' => self::SEED_RAZON_SOC,
            ]);

            $numRnd = $this->extractNumRnd($seedHtml);

            // Step 3: actual RUC query
            $html = $this->curlPost($ch, self::CONSULT_URL, [
                'accion'    => 'consPorRuc',
                'nroRuc'    => $ruc,
                'numRnd'    => $numRnd,
                'actReturn' => '1',
                'modo'      => '1',
            ]);
        } finally {
            curl_close($ch);
        }

        if ('' === $html) {
            $this->logger->warning('SunatRucScraper: empty response from SUNAT', ['ruc' => $ruc]);
            throw LookupException::notFound('RUC', $ruc);
        }

        return $this->parseHtml($ruc, $html);
    }

    // -------------------------------------------------------------------------
    // cURL helpers
    // -------------------------------------------------------------------------

    /** @return \CurlHandle */
    private function createCurlHandle(): \CurlHandle
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR      => '/dev/null',  // keep cookies in memory for this handle
            CURLOPT_COOKIEFILE     => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT        => 20,
        ]);

        return $ch;
    }

    private function curlGet(\CurlHandle $ch, string $url): string
    {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        $result = curl_exec($ch);

        return is_string($result) ? $result : '';
    }

    /**
     * @param array<string, string> $data
     */
    private function curlPost(\CurlHandle $ch, string $url, array $data): string
    {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        $result = curl_exec($ch);

        return is_string($result) ? $result : '';
    }

    private function extractNumRnd(string $html): string
    {
        preg_match('/<input[^>]+type=["\']hidden["\'][^>]+name=["\']numRnd["\'][^>]+value=["\']([^"\']*)["\']/', $html, $m);
        if (!empty($m[1])) {
            return $m[1];
        }
        // Try reverse attribute order
        preg_match('/<input[^>]+name=["\']numRnd["\'][^>]+value=["\']([^"\']*)["\']/', $html, $m);

        return $m[1] ?? '';
    }

    // -------------------------------------------------------------------------
    // HTML parsing (mirrors HtmlRecaptchaParser from peru-consult)
    // -------------------------------------------------------------------------

    /**
     * @throws LookupException
     */
    private function parseHtml(string $ruc, string $html): DocumentInfo
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $fields = $this->extractFields($xpath);

        if (empty($fields)) {
            $this->logger->warning('SunatRucScraper: could not parse fields', ['ruc' => $ruc]);
            throw LookupException::parseError('RUC', $ruc);
        }

        // "Número de RUC:" field value is "20100070970 - BANCO DE CREDITO DEL PERU"
        $rucRazSocial = $fields['Número de RUC:'] ?? $fields['RUC:'] ?? '';
        [$parsedRuc, $razonSocial] = $this->splitRucRazonSocial($rucRazSocial, $ruc);

        if ('' === $razonSocial) {
            throw LookupException::notFound('RUC', $ruc);
        }

        $estado    = strtoupper(trim($fields['Estado del Contribuyente:'] ?? $fields['Estado:'] ?? 'ACTIVO'));
        $condicion = strtoupper(trim(explode("\r\n", $fields['Condición del Contribuyente:'] ?? $fields['Condición:'] ?? 'HABIDO')[0]));
        $active    = str_contains($estado, 'ACTIVO') && str_contains($condicion, 'HABIDO');

        $address = trim($fields['Domicilio Fiscal:'] ?? $fields['Dirección del Domicilio Fiscal:'] ?? '');
        // SUNAT HTML contains excessive internal whitespace (padded table cells).
        // Normalize to single spaces so the value is clean before persistence.
        $address = preg_replace('/\s+/', ' ', $address) ?? $address;

        return new DocumentInfo(
            id: $parsedRuc ?: $ruc,
            name: $razonSocial,
            address: $address,
            active: $active,
        );
    }

    /**
     * Walks <div class="list-group"> items, extracting heading → text pairs.
     * Mirrors HtmlRecaptchaParser::getKeyValues().
     *
     * @return array<string, string>
     */
    private function extractFields(\DOMXPath $xpath): array
    {
        $dic = [];

        $listGroups = $xpath->query(
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' list-group ')]"
        );

        if (!$listGroups instanceof \DOMNodeList || 0 === $listGroups->count()) {
            return $dic;
        }

        $nodes = $listGroups->item(0)?->childNodes;
        if (!$nodes instanceof \DOMNodeList) {
            return $dic;
        }

        foreach ($nodes as $node) {
            if (XML_ELEMENT_NODE !== $node->nodeType) {
                continue;
            }

            $this->extractFromItem($xpath, $node, $dic);
        }

        return $dic;
    }

    /**
     * @param array<string, string> $dic
     */
    private function extractFromItem(\DOMXPath $xpath, \DOMNode $item, array &$dic): void
    {
        $headings = $xpath->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' list-group-item-heading ')]",
            $item
        );
        $texts = $xpath->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' list-group-item-text ')]",
            $item
        );

        if (!$headings instanceof \DOMNodeList || !$texts instanceof \DOMNodeList) {
            return;
        }

        // Special case: two headings, zero texts → "RUC: 20xxx - Razón Social"
        if (0 === $texts->count() && 2 === $headings->count()) {
            $label = trim($headings->item(0)->textContent);
            $value = trim($headings->item(1)->textContent);
            if ('' !== $label) {
                $dic[$label] = $value;
            }

            return;
        }

        for ($i = 0; $i < $headings->count(); ++$i) {
            $label = trim($headings->item($i)->textContent);
            if ('' === $label) {
                continue;
            }

            if ($texts->count() > $i) {
                $dic[$label] = trim($texts->item($i)->textContent);
            }
        }
    }

    /**
     * SUNAT packs "RUC - RazonSocial" in one string.
     *
     * @return array{string, string}  [ruc, razonSocial]
     */
    private function splitRucRazonSocial(string $text, string $fallbackRuc): array
    {
        $pos = strpos($text, ' - ');
        if (false === $pos) {
            return [$fallbackRuc, trim($text)];
        }

        return [
            trim(substr($text, 0, $pos)),
            trim(substr($text, $pos + 3)),
        ];
    }
}
