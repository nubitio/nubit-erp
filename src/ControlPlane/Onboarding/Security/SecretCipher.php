<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Security;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class SecretCipher
{
    private const string PREFIX = 'enc:v1:';
    private const int IV_BYTES = 12;
    private const int TAG_BYTES = 16;

    public function __construct(
        #[Autowire(env: 'APP_SECRET')]
        private string $appSecret,
    ) {
    }

    public function encrypt(string $secret): string
    {
        if ($secret === '') {
            return '';
        }

        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $secret,
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Could not protect secret.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $protectedSecret): string
    {
        if ($protectedSecret === '') {
            return '';
        }

        if (!str_starts_with($protectedSecret, self::PREFIX)) {
            // Backward compatibility for provision rows/messages created before secret wrapping.
            return $protectedSecret;
        }

        $payload = base64_decode(substr($protectedSecret, strlen(self::PREFIX)), true);
        if ($payload === false || strlen($payload) <= self::IV_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('Protected secret is malformed.');
        }

        $iv = substr($payload, 0, self::IV_BYTES);
        $tag = substr($payload, self::IV_BYTES, self::TAG_BYTES);
        $ciphertext = substr($payload, self::IV_BYTES + self::TAG_BYTES);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Protected secret could not be opened.');
        }

        return $plaintext;
    }

    private function key(): string
    {
        if (trim($this->appSecret) === '') {
            throw new RuntimeException('APP_SECRET is required to protect onboarding secrets.');
        }

        return hash('sha256', $this->appSecret, true);
    }
}
