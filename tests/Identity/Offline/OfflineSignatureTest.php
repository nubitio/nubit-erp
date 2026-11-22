<?php

declare(strict_types=1);

namespace App\Tests\Identity\Offline;

use App\Identity\Offline\OfflineSignature;
use PHPUnit\Framework\TestCase;

/**
 * The same vector is asserted in assets/offline/offlineCredential.test.ts: the till (Web Crypto)
 * and the server must produce the same bytes, or every signed sale would be refused.
 */
final class OfflineSignatureTest extends TestCase
{
    private const string MASTER = 'test-master-key';
    private const string KEY = '11111111-2222-3333-4444-555555555555';
    private const string BODY = '{"writeItems":[{"unitPrice":"9.80","description":"POLLO ENTERO X KG"}],"note":"ñ"}';

    public function testFixedVectorSharedWithTheTill(): void
    {
        $secret = OfflineSignature::secret(self::MASTER, 'cred-1', 7, 1_800_000_000);
        $bodyHash = OfflineSignature::bodyHash(self::BODY);
        $canonical = OfflineSignature::canonical('cred-1', self::KEY, 1_799_990_000, $bodyHash);

        self::assertSame('Ll+EAXHlBWi/euB9Qrm9/bdt0NkRonhTbu01mzKZ5Tg=', base64_encode($secret));
        self::assertSame('d50d2693cc3712f3661164bc96cdc4c42024feddb931b5ac10e89759c377cb01', $bodyHash);
        self::assertSame(
            "v1\ncred-1\n"
            . self::KEY
            . "\n1799990000\nd50d2693cc3712f3661164bc96cdc4c42024feddb931b5ac10e89759c377cb01",
            $canonical,
        );
        self::assertSame('db2656f676cda23ee77011107e5678545649cc62738c9cd9c8b6049643e870bf', OfflineSignature::sign(
            $secret,
            $canonical,
        ));
    }

    public function testTheSecretDependsOnEverythingItIsDerivedFrom(): void
    {
        $base = OfflineSignature::secret(self::MASTER, 'cred-1', 7, 1_800_000_000);

        self::assertNotSame($base, OfflineSignature::secret('other-master', 'cred-1', 7, 1_800_000_000));
        self::assertNotSame($base, OfflineSignature::secret(self::MASTER, 'cred-2', 7, 1_800_000_000));
        self::assertNotSame($base, OfflineSignature::secret(self::MASTER, 'cred-1', 8, 1_800_000_000));
        self::assertNotSame($base, OfflineSignature::secret(self::MASTER, 'cred-1', 7, 1_800_000_001));
    }

    public function testChangingAnythingSignedChangesTheSignature(): void
    {
        $secret = OfflineSignature::secret(self::MASTER, 'cred-1', 7, 1_800_000_000);
        $sign = static fn(
            string $id,
            string $key,
            int $at,
            string $body,
        ): string => OfflineSignature::sign($secret, OfflineSignature::canonical(
            $id,
            $key,
            $at,
            OfflineSignature::bodyHash($body),
        ));
        $original = $sign('cred-1', self::KEY, 1_799_990_000, self::BODY);

        self::assertNotSame($original, $sign('cred-2', self::KEY, 1_799_990_000, self::BODY));
        self::assertNotSame($original, $sign(
            'cred-1',
            '99999999-2222-3333-4444-555555555555',
            1_799_990_000,
            self::BODY,
        ));
        self::assertNotSame($original, $sign('cred-1', self::KEY, 1_799_990_001, self::BODY));
        self::assertNotSame($original, $sign(
            'cred-1',
            self::KEY,
            1_799_990_000,
            str_replace('9.80', '0.10', self::BODY),
        ));
    }

    public function testComparisonIgnoresCaseButNotContent(): void
    {
        self::assertTrue(OfflineSignature::matches('abcdef', 'ABCDEF'));
        self::assertFalse(OfflineSignature::matches('abcdef', 'abcdee'));
        self::assertFalse(OfflineSignature::matches('abcdef', ''));
    }
}
