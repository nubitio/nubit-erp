<?php

declare(strict_types=1);

namespace App\Efact\Web;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Generates and stores a cryptographic nonce per HTTP request.
 *
 * The nonce is created once per request (lazy) and shared between the
 * CspNonceListener (which writes it into the Content-Security-Policy header)
 * and the Twig csp_nonce() function (which injects it into inline <script>
 * and <style> tags).
 */
final class CspNonceService
{
    /** @var array<int|string, string> nonce keyed by request object-id */
    private array $nonces = [];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Returns the nonce for the current request, generating it on first call.
     */
    public function getNonce(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $key = $request !== null ? spl_object_id($request) : 0;

        if (!isset($this->nonces[$key])) {
            $this->nonces[$key] = rtrim(base64_encode(random_bytes(16)), '=');
        }

        return $this->nonces[$key];
    }
}
