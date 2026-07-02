<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

final readonly class CertificateMetadataExtractor
{
    public function extract(string $pem): CertificateMetadata
    {
        $resource = @openssl_x509_read($pem);
        if ($resource === false) {
            throw new \InvalidArgumentException('Invalid certificate PEM.');
        }

        $parsed = openssl_x509_parse($resource, false);
        if (!is_array($parsed)) {
            throw new \InvalidArgumentException('Invalid certificate PEM.');
        }

        $fingerprint = openssl_x509_fingerprint($pem, 'sha256');
        if ($fingerprint === false) {
            throw new \InvalidArgumentException('Invalid certificate PEM fingerprint.');
        }

        $subject = isset($parsed['subject']) && is_array($parsed['subject']) ? $parsed['subject'] : [];
        $issuer = isset($parsed['issuer']) && is_array($parsed['issuer']) ? $parsed['issuer'] : [];
        $validFrom = isset($parsed['validFrom_time_t']) ? (int) $parsed['validFrom_time_t'] : 0;
        $validTo = isset($parsed['validTo_time_t']) ? (int) $parsed['validTo_time_t'] : 0;

        if ($validFrom <= 0 || $validTo <= 0) {
            throw new \InvalidArgumentException('Certificate validity dates are missing.');
        }

        return new CertificateMetadata(
            subjectCn: $this->commonName($subject),
            subject: $subject,
            issuerCn: $this->commonName($issuer),
            issuer: $issuer,
            serialNumber: isset($parsed['serialNumberHex'])
                ? (string) $parsed['serialNumberHex']
                : (string) ($parsed['serialNumber'] ?? ''),
            fingerprintSha256: strtolower(str_replace(':', '', $fingerprint)),
            validFrom: new \DateTimeImmutable()->setTimestamp($validFrom),
            expiresAt: new \DateTimeImmutable()->setTimestamp($validTo),
        );
    }

    /**
     * @param array<string, mixed> $name
     */
    private function commonName(array $name): ?string
    {
        foreach (['CN', 'commonName'] as $key) {
            if (isset($name[$key]) && is_scalar($name[$key]) && trim((string) $name[$key]) !== '') {
                return trim((string) $name[$key]);
            }
        }

        return null;
    }
}
