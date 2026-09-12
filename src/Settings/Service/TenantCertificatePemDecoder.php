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
        $cert = isset($certs['cert']) && is_string($certs['cert']) ? $certs['cert'] : '';
        if ($cert === '') {
            throw new \InvalidArgumentException('El PFX no contiene un certificado valido.');
        }
        $privateKey = isset($certs['pkey']) && is_string($certs['pkey']) ? $certs['pkey'] : '';
        return $cert . ($privateKey === '' ? '' : "\n" . $privateKey);
    }
}
