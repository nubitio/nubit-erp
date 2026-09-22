<?php

declare(strict_types=1);

namespace App\Settings\Service;

use SensitiveParameter;

final class TenantCertificatePemDecoder
{
    public function decode(string $contents, #[SensitiveParameter] string $password): string
    {
        if (str_contains($contents, '-----BEGIN CERTIFICATE-----')) {
            if (!str_contains($contents, 'PRIVATE KEY-----')) {
                throw new \InvalidArgumentException('El PEM debe incluir el certificado y su clave privada.');
            }
            return $contents;
        }

        $certs = [];
        if (!openssl_pkcs12_read($contents, $certs, $password)) {
            throw new \InvalidArgumentException('No se pudo leer el certificado PFX. Verifica la contraseña.');
        }
        $certValue = $certs['cert'] ?? null;
        $cert = is_string($certValue) ? $certValue : '';
        if ($cert === '') {
            throw new \InvalidArgumentException('El PFX no contiene un certificado valido.');
        }
        $privateKeyValue = $certs['pkey'] ?? null;
        $privateKey = is_string($privateKeyValue) ? $privateKeyValue : '';
        return $cert . ($privateKey === '' ? '' : "\n" . $privateKey);
    }
}
