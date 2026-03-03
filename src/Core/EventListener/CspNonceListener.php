<?php

declare(strict_types=1);

namespace App\Core\EventListener;

use App\Core\Security\CspNonceService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Injects a per-request Content-Security-Policy header with a nonce
 * for HTML responses only (API/JSON responses are left untouched).
 *
 * The nonce value is shared with the Twig csp_nonce() function so that
 * inline <script nonce="…"> tags in templates are allowed by the policy.
 *
 * Caddy serves a static CSP for non-PHP assets; PHP-rendered HTML pages
 * get their own stricter policy here.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
final readonly class CspNonceListener
{
    public function __construct(
        private CspNonceService $nonceService,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $contentType = $response->headers->get('Content-Type', '');

        // Only inject CSP on HTML responses
        if (!str_contains($contentType, 'text/html')) {
            return;
        }

        $nonce = $this->nonceService->getNonce();

        $response->headers->set(
            'Content-Security-Policy',
            implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "worker-src 'self' blob:",
            ])
        );
    }
}
