<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Infrastructure;

/**
 * Centraliza la construcción de URLs para lycet y el enmascaramiento del token
 * en mensajes de error / trazas de observabilidad.
 *
 * El token nunca debe aparecer en logs, trazas de OpenTelemetry ni mensajes de
 * excepción propagados al cliente.
 */
trait LycetUrlBuilder
{
    /**
     * Construye una URL lycet con el token como query param.
     *
     * Uso: $this->lycetUrl('invoice/send')
     *      $this->lycetUrl('invoice/status', ['ruc' => '...', 'tipo' => '01'])
     *
     * @param array<string,string|int|null> $extraParams
     */
    private function lycetUrl(string $path, array $extraParams = []): string
    {
        $base = rtrim($this->apiBaseUrl, '/');
        $url  = "{$base}/api/v1/{$path}?token={$this->apiToken}";

        if ($extraParams !== []) {
            $url .= '&' . http_build_query($extraParams);
        }

        return $url;
    }

    /**
     * Devuelve la URL con el token reemplazado por "***" para logs y trazas.
     *
     * @param array<string,string|int|null> $extraParams
     */
    private function lycetUrlSafe(string $path, array $extraParams = []): string
    {
        $base = rtrim($this->apiBaseUrl, '/');
        $url  = "{$base}/api/v1/{$path}?token=***";

        if ($extraParams !== []) {
            $url .= '&' . http_build_query($extraParams);
        }

        return $url;
    }

    /**
     * Enmascara el token en el mensaje de una excepción y la re-lanza.
     *
     * Si el token no aparece en el mensaje, re-lanza la excepción original sin
     * modificarla para no alterar el tipo ni la identidad del objeto.
     * Si el token sí aparece, wrappea en RuntimeException con el mensaje
     * enmascarado para evitar que el token aparezca en trazas.
     *
     * @throws \Throwable
     */
    private function maskAndRethrow(\Throwable $e): never
    {
        $message = $e->getMessage();

        if (!str_contains($message, 'token=' . $this->apiToken)) {
            throw $e;
        }

        $masked = str_replace(
            'token=' . $this->apiToken,
            'token=***',
            $message,
        );

        throw new \RuntimeException($masked, $e->getCode(), $e);
    }
}
