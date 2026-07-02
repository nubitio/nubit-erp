<?php

declare(strict_types=1);

namespace App\Efact\Web;

use App\Efact\Web\CspNonceService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Injects a per-request Content-Security-Policy header with a nonce
 * for HTML responses only (API/JSON responses are left untouched).
 *
 * The nonce value is shared with the Twig csp_nonce() function so that
 * inline <script nonce="..."> tags in templates are allowed by the policy.
 *
 * Caddy serves a static CSP for non-PHP assets; PHP-rendered HTML pages
 * get their own stricter policy here.
 *
 * Asset images are served from S3 (or a CDN proxy when S3_PUBLIC_URL is set).
 * The img-src directive is derived from the public base URL so it only needs
 * updating in .env, not in code.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
final readonly class CspNonceListener
{
    private string $assetHost;
    private string $mercureHost;

    public function __construct(
        private CspNonceService $nonceService,
        #[Autowire(env: 'S3_PUBLIC_URL')]
        string $s3PublicUrl,
        #[Autowire(env: 'S3_ENDPOINT')]
        string $s3Endpoint,
        #[Autowire(env: 'S3_BUCKET_NAME')]
        string $s3BucketName,
        #[Autowire(env: 'MERCURE_PUBLIC_URL')]
        string $mercurePublicUrl,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
    ) {
        $s3PublicUrl = trim($s3PublicUrl);
        $base = $s3PublicUrl;

        if ($base === '') {
            $base = str_contains($s3BucketName, '.') && !str_contains($s3BucketName, '/')
                ? 'https://' . $s3BucketName
                : $s3Endpoint;
        }

        $host = parse_url($base, PHP_URL_HOST) ?? '';
        $scheme = parse_url($base, PHP_URL_SCHEME) ?? 'https';
        $this->assetHost = $host !== '' ? $scheme . '://' . $host : '';

        // Build the Mercure hub origin for connect-src (e.g. https://app.efact.app).
        $mercureHost = parse_url(trim($mercurePublicUrl), PHP_URL_HOST) ?? '';
        $mercureScheme = parse_url(trim($mercurePublicUrl), PHP_URL_SCHEME) ?? 'https';
        $mercurePort = parse_url(trim($mercurePublicUrl), PHP_URL_PORT);
        $this->mercureHost = $mercureHost !== ''
            ? $mercureScheme . '://' . $mercureHost . ($mercurePort !== null ? ':' . $mercurePort : '')
            : '';
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Skip CSP in dev — Vite HMR and browser tooling need unrestricted script/connect access.
        if ($this->environment === 'dev') {
            return;
        }

        $response = $event->getResponse();
        $contentType = $response->headers->get('Content-Type', '');

        // Only inject CSP on HTML responses
        if (!str_contains($contentType, 'text/html')) {
            return;
        }

        $nonce = $this->nonceService->getNonce();

        // img-src: allow self, data URIs, and the S3/CDN asset host.
        // Switch to https://assets.efact.app by setting S3_PUBLIC_URL in .env.
        $imgSrc = "img-src 'self' data:";
        if ($this->assetHost !== '') {
            $imgSrc .= ' ' . $this->assetHost;
        }

        // The Mercure hub is proxied at /.well-known/mercure on the SAME domain as the app
        // (same-origin). However, when PHP runs behind an SSL-terminating proxy without
        // X-Forwarded-Proto, getSchemeAndHttpHost() may return http:// while the browser
        // is on https://. We include both scheme variants of the tenant host so the
        // EventSource URL is always covered regardless of proxy configuration.
        $tenantHost = $event->getRequest()->getHttpHost(); // e.g. jawka.efact.app
        $connectSrcExtra = $tenantHost !== '' ? "https://{$tenantHost} http://{$tenantHost}" : $this->mercureHost; // fallback to static env var

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            $imgSrc,
            "font-src 'self' data: https://cdn.jsdelivr.net",
            // 'self' for API calls; tenant host (both schemes) for Mercure SSE;
            // localhost HTTP for efact-hardware-agent;
            // localhost ws/wss for QZ Tray desktop print agent.
            "connect-src 'self' {$connectSrcExtra} http://127.0.0.1:8765 http://localhost:8765 ws://localhost:* wss://localhost:* ws://localhost.qz.io:* wss://localhost.qz.io:*",
            "frame-src 'self'",
            "worker-src 'self' blob:",
        ]));
    }
}
