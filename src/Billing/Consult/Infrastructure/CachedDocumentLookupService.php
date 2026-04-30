<?php

declare(strict_types=1);

namespace App\Billing\Consult\Infrastructure;

use App\Billing\Consult\Domain\DocumentInfo;
use App\Billing\Consult\Domain\LookupException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates RUC and DNI lookups with transparent PSR-6 caching.
 *
 * Lookup chain
 * ────────────
 * RUC
 *   1. SunatRucScraper   — 3-step SUNAT scraping (free, no token)
 *   2. DecolectaClient   — fallback if SUNAT fails and DECOLECTA_TOKEN is set
 *
 * DNI
 *   1. SunatDniScraper   — constructs natural-person RUC and queries SUNAT (free)
 *   2. DecolectaClient   — fallback via RENIEC endpoint if DECOLECTA_TOKEN is set
 *
 * Cache strategy (Redis)
 * ──────────────────────
 *   RUC  → 7 days  (company data changes rarely)
 *   DNI  → 30 days (person names are stable)
 *
 * Keys are global (not tenant-scoped): SUNAT/RENIEC data is public information —
 * the same document number returns the same result regardless of tenant.
 */
final class CachedDocumentLookupService
{
    private const int TTL_RUC_SECONDS = 7 * 24 * 60 * 60;   // 7 days
    private const int TTL_DNI_SECONDS = 30 * 24 * 60 * 60;  // 30 days

    public function __construct(
        private readonly SunatRucScraper $rucScraper,
        private readonly SunatDniScraper $dniScraper,
        private readonly DecolectaClient $decolecta,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws LookupException
     * @throws InvalidArgumentException
     */
    public function lookupRuc(string $ruc): DocumentInfo
    {
        $key = $this->cacheKey('ruc', $ruc);

        $cached = $this->fromCache($key);
        if ($cached instanceof DocumentInfo) {
            return $cached;
        }

        $info = $this->tryRuc($ruc);
        $this->toCache($key, $info, self::TTL_RUC_SECONDS);

        return $info;
    }

    /**
     * @throws LookupException
     * @throws InvalidArgumentException
     */
    public function lookupDni(string $dni): DocumentInfo
    {
        $key = $this->cacheKey('dni', $dni);

        $cached = $this->fromCache($key);
        if ($cached instanceof DocumentInfo) {
            return $cached;
        }

        $info = $this->tryDni($dni);
        $this->toCache($key, $info, self::TTL_DNI_SECONDS);

        return $info;
    }

    // -------------------------------------------------------------------------
    // Lookup chains
    // -------------------------------------------------------------------------

    /** @throws LookupException */
    private function tryRuc(string $ruc): DocumentInfo
    {
        // Primary: SUNAT scraping (free)
        try {
            return $this->rucScraper->lookup($ruc);
        } catch (LookupException $primary) {
            $this->logger->warning('CachedDocumentLookupService: SUNAT RUC scraping failed, trying Decolecta', [
                'ruc' => $ruc, 'error' => $primary->getMessage(),
            ]);
        }

        // Fallback: Decolecta (requires DECOLECTA_TOKEN)
        if ($this->decolecta->isConfigured()) {
            return $this->decolecta->lookupRuc($ruc);
        }

        throw LookupException::notFound('RUC', $ruc);
    }

    /** @throws LookupException */
    private function tryDni(string $dni): DocumentInfo
    {
        // Primary: SUNAT natural-person RUC construction (free)
        try {
            return $this->dniScraper->lookup($dni);
        } catch (LookupException $primary) {
            $this->logger->info('CachedDocumentLookupService: SUNAT DNI lookup failed, trying Decolecta', [
                'dni' => $dni, 'error' => $primary->getMessage(),
            ]);
        }

        // Fallback: Decolecta RENIEC endpoint (requires DECOLECTA_TOKEN)
        if ($this->decolecta->isConfigured()) {
            return $this->decolecta->lookupDni($dni);
        }

        throw LookupException::notFound('DNI', $dni);
    }

    // -------------------------------------------------------------------------
    // Cache helpers
    // -------------------------------------------------------------------------

    private function cacheKey(string $type, string $id): string
    {
        return sprintf('doc_lookup.%s.%s', $type, preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $id));
    }

    private function fromCache(string $key): ?DocumentInfo
    {
        try {
            $item = $this->cache->getItem($key);
            if ($item->isHit()) {
                $value = $item->get();
                if ($value instanceof DocumentInfo) {
                    $this->logger->debug('DocumentLookup cache HIT', ['key' => $key]);

                    return $value;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('DocumentLookup cache read error', [
                'key' => $key, 'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function toCache(string $key, DocumentInfo $info, int $ttl): void
    {
        try {
            $item = $this->cache->getItem($key);
            $item->set($info);
            $item->expiresAfter($ttl);
            $this->cache->save($item);
            $this->logger->debug('DocumentLookup cache SET', ['key' => $key, 'ttl' => $ttl]);
        } catch (\Throwable $e) {
            $this->logger->warning('DocumentLookup cache write error', [
                'key' => $key, 'error' => $e->getMessage(),
            ]);
        }
    }
}
