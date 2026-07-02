<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Certificate;

use App\ControlPlane\Certificate\CertificateMetadataExtractor;
use PHPUnit\Framework\TestCase;

final class CertificateMetadataExtractorTest extends TestCase
{
    public function testExtractsMetadataFromPem(): void
    {
        $pem = $this->createSelfSignedCertificatePem('Demo Cert');
        $metadata = new CertificateMetadataExtractor()->extract($pem);

        self::assertSame('Demo Cert', $metadata->subjectCn);
        self::assertNotSame('', $metadata->fingerprintSha256);
        self::assertGreaterThan($metadata->validFrom, $metadata->expiresAt);
    }

    public function testRejectsInvalidPem(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid certificate PEM.');

        new CertificateMetadataExtractor()->extract('not-a-cert');
    }

    private function createSelfSignedCertificatePem(string $commonName): string
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($privateKey);

        $csr = openssl_csr_new(['commonName' => $commonName], $privateKey);
        self::assertNotFalse($csr);

        $certificate = openssl_csr_sign($csr, null, $privateKey, 365);
        self::assertNotFalse($certificate);

        $exported = openssl_x509_export($certificate, $pem);
        self::assertTrue($exported);

        return $pem;
    }
}
