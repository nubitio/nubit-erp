<?php

declare(strict_types=1);

namespace App\Integration\Security;

/**
 * The parts of a freshly minted key. `secret` is the only place the full key
 * ever exists in cleartext — it is shown once at creation and then only its
 * {@see self::$prefix} and {@see self::$tokenHash} survive.
 */
final readonly class ApiKeyMaterial
{
    public function __construct(
        #[\SensitiveParameter]
        public string $secret,
        public string $prefix,
        public string $tokenHash,
        public string $tenant,
        public string $environment,
    ) {}
}
