<?php

declare(strict_types=1);

namespace App\Integration\Security;

/**
 * Mints and parses integration API keys.
 *
 * Shape: `nb_<env>_<tenant>_<secret>`
 *   - `env`     — `live` or `test`
 *   - `tenant`  — the tenant slug, so the authenticator can pick the right
 *                 database before it has touched one (there is no shared
 *                 table to look the key up in). The slug is the public
 *                 subdomain, so putting it here leaks nothing.
 *   - `secret`  — 43 base62 chars (~256 bits)
 *
 * The stored prefix is everything up to and including the first 8 characters
 * of the secret; that is unique enough to index on and select a single row.
 */
final class ApiKeyGenerator
{
    private const string PATTERN = '/^nb_(live|test)_([a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?)_([0-9A-Za-z]{43})$/';
    private const int SECRET_CHARS = 43;
    private const int PREFIX_SECRET_CHARS = 8;
    private const string ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function generate(string $tenant, bool $test = false): ApiKeyMaterial
    {
        $tenant = strtolower(trim($tenant));
        if (1 !== preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/', $tenant)) {
            throw new \InvalidArgumentException(sprintf('Invalid tenant slug "%s".', $tenant));
        }

        $environment = $test ? 'test' : 'live';
        $key = sprintf('nb_%s_%s_%s', $environment, $tenant, self::randomSecret());

        return new ApiKeyMaterial(
            secret: $key,
            prefix: self::prefixOf($key),
            tokenHash: hash('sha256', $key),
            tenant: $tenant,
            environment: $environment,
        );
    }

    /**
     * @return array{env: string, tenant: string, prefix: string, tokenHash: string}|null
     */
    public function parse(string $key): ?array
    {
        $m = [];
        if (1 !== preg_match(self::PATTERN, $key, $m)) {
            return null;
        }

        return [
            'env' => $m[1],
            'tenant' => $m[2],
            'prefix' => self::prefixOf($key),
            'tokenHash' => hash('sha256', $key),
        ];
    }

    private static function prefixOf(string $key): string
    {
        $lastUnderscore = strrpos($key, '_');
        $secretStart = false === $lastUnderscore ? 0 : $lastUnderscore + 1;

        return substr($key, 0, $secretStart + self::PREFIX_SECRET_CHARS);
    }

    private static function randomSecret(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < self::SECRET_CHARS; ++$i) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
