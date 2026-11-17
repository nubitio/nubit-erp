<?php

declare(strict_types=1);

namespace App\Efact\Web;

use App\Efact\Contract\LogoProviderInterface;
use Nubit\ApiPlatform\Http\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends BaseController
{
    /**
     * Server-side namespaces the tenant SPA must never answer (ADR-020, D2).
     *
     * An unknown URL below any of these is a genuine 404 — and for `/api` a JSON
     * one — not the HTML shell. Static directories are listed because Caddy only
     * serves files with a known extension; anything else there would otherwise
     * fall through to the shell. `receipt` and `buscar` are the public voucher
     * pages; see SpaRoutingTest for the SPA path that still collides with `receipt`.
     */
    public const array RESERVED_PREFIXES = [
        'api',
        'admin',
        'account',
        'register',
        'buscar',
        'receipt',
        'build',
        'uploads',
        'health',
        'logo',
        'webhook',
        'css',
        'js',
        'images',
        'themes',
        'assets',
        'bundles',
        '.well-known',
        'sw.js',
    ];

    /**
     * Anything else that is not a file (no extension) is a client-side route.
     * Defined as a requirement rather than by enumerating the ~130 SPA paths,
     * which would drift from `app-routes.tsx`; tests/Fixtures/spa-routes.json
     * and SpaRoutingTest keep the two in step instead.
     *
     * End of path is written `(?!.)`, never `$`: Symfony's compiled URL matcher drops
     * an end anchor inside a lookahead, which silently turned the "no file
     * extension" rule off (a missing asset came back as the HTML shell). The optional
     * `/?` matters too: on a 404 the router retries a GET with a trailing slash
     * (`/favicon.ico` → `/favicon.ico/`), and that variant must be rejected as well,
     * otherwise the redirect lands on the shell.
     */
    public const string SPA_PATH_REQUIREMENT = '(?!(?:api|admin|account|register|buscar|receipt|build|uploads|health|logo|webhook|css|js|images|themes|assets|bundles|\\.well-known|sw\\.js|workbox-[^/]*|_[^/]*)(?:/|(?!.)))(?!.*\\.[A-Za-z0-9]+/?(?!.)).*';

    #[Route('/', name: 'home_index', methods: ['GET'])]
    #[Route(
        '/{path}',
        name: 'spa_catch_all',
        requirements: ['path' => self::SPA_PATH_REQUIREMENT],
        defaults: ['path' => ''],
        methods: ['GET'],
        priority: -100,
    )]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/logo', name: 'logo', methods: ['GET'])]
    public function logo(LogoProviderInterface $logoProvider): Response
    {
        $logoUrl = $logoProvider();

        return $this->redirect($logoUrl);
    }
}
