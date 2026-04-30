<?php

declare(strict_types=1);

namespace App\Billing\Consult\Domain;

/**
 * Thrown when a document lookup fails (network error, not found, parse error, etc.).
 */
final class LookupException extends \RuntimeException
{
    public static function notFound(string $docType, string $id): self
    {
        return new self(sprintf(
            'No se encontró información para el %s: %s',
            strtoupper($docType),
            $id
        ));
    }

    public static function networkError(string $docType, string $id, \Throwable $previous): self
    {
        return new self(
            sprintf('Error de conexión al consultar %s %s: %s', strtoupper($docType), $id, $previous->getMessage()),
            0,
            $previous
        );
    }

    public static function parseError(string $docType, string $id): self
    {
        return new self(sprintf(
            'No se pudo interpretar la respuesta al consultar %s %s',
            strtoupper($docType),
            $id
        ));
    }

    public static function notConfigured(string $service): self
    {
        return new self(sprintf(
            'El servicio de consulta "%s" no está configurado. Revisa la variable de entorno correspondiente.',
            $service
        ));
    }
}
