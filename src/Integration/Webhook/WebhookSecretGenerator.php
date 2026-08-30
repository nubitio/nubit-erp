<?php

declare(strict_types=1);

namespace App\Integration\Webhook;

/**
 * A webhook signing secret: `whsec_` + 40 base62 chars.
 */
final class WebhookSecretGenerator
{
    private const string ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    private const int CHARS = 40;

    public function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < self::CHARS; ++$i) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return 'whsec_' . $out;
    }
}
