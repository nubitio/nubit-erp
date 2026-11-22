<?php

declare(strict_types=1);

namespace App\Identity\Offline;

/**
 * The signature a till puts on a sale made without connection, so the server can tell who
 * made it no matter whose session delivers it later.
 *
 * Mirrored in assets/offline/offlineCredential.ts: both sides must produce the same bytes,
 * and tests/Identity/Offline/OfflineSignatureTest.php and assets/offline/offlineCredential.test.ts
 * assert the same fixed vector.
 *
 *   secret    = HMAC-SHA256(master key, "offline-credential|<id>|<user id>|<expires at>")   (32 bytes)
 *   canonical = "v1" LF <credential id> LF <Idempotency-Key> LF <signed at, seconds> LF <sha256 hex of the body>
 *   signature = HMAC-SHA256(secret, canonical), lowercase hex
 *
 * The secret is derived, not stored: the server re-derives it from the credential row.
 */
final class OfflineSignature
{
    public const string VERSION = 'v1';

    public static function secret(string $masterKey, string $credentialId, int $userId, int $expiresAt): string
    {
        return hash_hmac(
            'sha256',
            implode('|', ['offline-credential', $credentialId, $userId, $expiresAt]),
            $masterKey,
            true,
        );
    }

    public static function bodyHash(string $body): string
    {
        return hash('sha256', $body);
    }

    public static function canonical(
        string $credentialId,
        string $idempotencyKey,
        int $signedAt,
        string $bodyHash,
    ): string {
        return implode("\n", [self::VERSION, $credentialId, $idempotencyKey, (string) $signedAt, $bodyHash]);
    }

    public static function sign(#[\SensitiveParameter] string $secret, string $canonical): string
    {
        return hash_hmac('sha256', $canonical, $secret);
    }

    public static function matches(string $expected, string $given): bool
    {
        return hash_equals($expected, strtolower($given));
    }
}
