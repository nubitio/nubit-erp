<?php

declare(strict_types=1);

namespace App\Billing\Consult\Infrastructure;

use App\Billing\Consult\Domain\DocumentInfo;
use App\Billing\Consult\Domain\LookupException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Consults RUC and DNI via the Decolecta API (https://decolecta.com).
 *
 * Docs: https://decolecta.gitbook.io/docs
 *
 * Endpoints
 * ─────────
 * DNI  GET https://api.decolecta.com/v1/reniec/dni?numero={dni}
 * RUC  GET https://api.decolecta.com/v1/sunat/ruc?numero={ruc}
 *
 * Auth: Authorization: Bearer <DECOLECTA_TOKEN>
 * Token: register at https://decolecta.com/profile (free tier available)
 *
 * DNI response shape:
 *   { "first_name", "first_last_name", "second_last_name", "full_name", "document_number" }
 *
 * RUC response shape:
 *   { "razon_social", "numero_documento", "estado", "condicion", "direccion", ... }
 *
 * This class is used as a fallback in CachedDocumentLookupService when the
 * primary SUNAT scraping strategies fail or are unavailable.
 */
final class DecolectaClient
{
    private const string BASE_URL = 'https://api.decolecta.com/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        /** Injected from %env(DECOLECTA_TOKEN)% — null/empty string means "not configured". */
        private readonly ?string $decolectaToken,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->decolectaToken && '' !== $this->decolectaToken;
    }

    // -------------------------------------------------------------------------
    // DNI
    // -------------------------------------------------------------------------

    /**
     * @throws LookupException
     */
    public function lookupDni(string $dni): DocumentInfo
    {
        $this->assertConfigured();

        $data = $this->get('/reniec/dni', ['numero' => $dni], 'DNI', $dni);

        $fullName = trim((string) ($data['full_name'] ?? ''));

        if ('' === $fullName) {
            // Build from parts as fallback
            $firstName  = trim((string) ($data['first_name'] ?? ''));
            $firstLast  = trim((string) ($data['first_last_name'] ?? ''));
            $secondLast = trim((string) ($data['second_last_name'] ?? ''));
            $fullName   = implode(' ', array_filter([$firstLast, $secondLast, $firstName]));
        }

        if ('' === $fullName) {
            throw LookupException::notFound('DNI', $dni);
        }

        return new DocumentInfo(
            id: (string) ($data['document_number'] ?? $dni),
            name: $fullName,
            address: '',    // RENIEC does not expose address
            active: true,
        );
    }

    // -------------------------------------------------------------------------
    // RUC
    // -------------------------------------------------------------------------

    /**
     * @throws LookupException
     */
    public function lookupRuc(string $ruc): DocumentInfo
    {
        $this->assertConfigured();

        $data = $this->get('/sunat/ruc', ['numero' => $ruc], 'RUC', $ruc);

        $name = trim((string) ($data['razon_social'] ?? ''));
        if ('' === $name) {
            throw LookupException::notFound('RUC', $ruc);
        }

        $estado    = strtoupper(trim((string) ($data['estado'] ?? 'ACTIVO')));
        $condicion = strtoupper(trim((string) ($data['condicion'] ?? 'HABIDO')));
        $active    = str_contains($estado, 'ACTIVO') && str_contains($condicion, 'HABIDO');

        return new DocumentInfo(
            id: (string) ($data['numero_documento'] ?? $ruc),
            name: $name,
            address: trim((string) ($data['direccion'] ?? '')),
            active: $active,
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     *
     * @throws LookupException
     */
    private function get(string $path, array $query, string $docType, string $id): array
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . $path, [
                'headers' => [
                    'Authorization' => 'Bearer ' . ($this->decolectaToken ?? ''),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'query'   => $query,
                'timeout' => 10,
            ]);

            $status = $response->getStatusCode();

            if (404 === $status) {
                throw LookupException::notFound($docType, $id);
            }

            if (401 === $status || 403 === $status) {
                $this->logger->error('DecolectaClient: token inválido o expirado', [
                    'path' => $path,
                    'status' => $status,
                ]);
                throw LookupException::notConfigured('Decolecta (token inválido o expirado)');
            }

            if ($status < 200 || $status >= 300) {
                $this->logger->warning('DecolectaClient: respuesta inesperada', [
                    'path' => $path, 'status' => $status,
                ]);
                throw LookupException::notFound($docType, $id);
            }

            /** @var array<string, mixed> $data */
            $data = $response->toArray();

            return $data;
        } catch (LookupException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('DecolectaClient: error de red', [
                'path' => $path, 'error' => $e->getMessage(),
            ]);
            throw LookupException::networkError($docType, $id, $e);
        }
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw LookupException::notConfigured('Decolecta (configura DECOLECTA_TOKEN en .env)');
        }
    }
}
