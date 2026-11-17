<?php

declare(strict_types=1);

namespace App\Tests\Efact\Web;

use App\Efact\Web\HomeController;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * ADR-020, D6: the tenant SPA is served from real paths, so every client route
 * must reach the SPA catch-all and no server namespace may be swallowed by it.
 *
 * tests/Fixtures/spa-routes.json is generated from the client route tables by
 * assets/app/shell/spaPaths.test.ts; this test is the other half of that contract.
 */
final class SpaRoutingTest extends KernelTestCase
{
    private const string CATCH_ALL = 'spa_catch_all';

    /**
     * Client paths that Symfony answers itself, so a reload or an e-mailed link would
     * reach the wrong page. Each entry is debt to be paid by renaming the client route,
     * and the list is asserted to match exactly, so it can neither grow unnoticed nor
     * linger after a fix. Empty since ADR-020 step 2, which renamed the stock-receipt
     * screen from `/receipt` to `/stock-receipt` (`/receipt/{payload}` is the PUBLIC
     * voucher page: printed QR codes and e-mails point at it, so it cannot move).
     */
    private const array KNOWN_COLLISIONS = [];

    private RouterInterface $router;

    protected function setUp(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        static::assertInstanceOf(RouterInterface::class, $router);
        $this->router = $router;
    }

    public function testEveryClientRouteReachesTheCatchAllExceptTheKnownCollisions(): void
    {
        $collisions = [];

        foreach ($this->clientUrls() as $url) {
            if (self::CATCH_ALL === $this->routeFor($url)) {
                continue;
            }

            $collisions[] = $url;
        }

        sort($collisions);
        $expected = self::KNOWN_COLLISIONS;
        sort($expected);

        static::assertSame(
            $expected,
            $collisions,
            'A client route now collides with a server route (or a known collision was fixed: delete it from '
            . 'KNOWN_COLLISIONS). Rename the client path; see ADR-020.',
        );
    }

    public function testTheRootAndTheKnownServerPagesKeepTheirOwnRoutes(): void
    {
        $expected = [
            '/' => 'home_index',
            '/admin/login' => 'admin_login',
            '/register' => 'onboarding_register',
            '/account/login' => 'onboarding_account_login',
            '/health' => 'health_check',
            '/logo' => 'logo',
            '/buscar' => null, // route name not asserted, only that it is not the catch-all
            '/receipt/some-payload' => 'receipt_show',
        ];

        foreach ($expected as $url => $name) {
            $actual = $this->routeFor($url);
            static::assertNotSame(self::CATCH_ALL, $actual, sprintf('%s must not be answered by the SPA.', $url));
            if (null !== $name) {
                static::assertSame($name, $actual, sprintf('%s resolved to the wrong route.', $url));
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonSpaUrls(): iterable
    {
        yield 'unknown api path stays a JSON 404' => ['/api/spa-probe-does-not-exist'];
        yield 'missing build asset is a 404, not HTML' => ['/build/missing-chunk.js'];
        yield 'missing static dir file' => ['/css/missing.css'];
        yield 'file with extension at the root' => ['/favicon.ico'];
        yield 'missing file, trailing-slash retry' => ['/favicon.ico/'];
        yield 'missing text file' => ['/robots.txt'];
        yield 'dotted name in a subdirectory' => ['/a/b.css'];
        yield 'service worker is a file' => ['/sw.js'];
        yield 'workbox runtime is a file' => ['/workbox-abc123.js'];
        yield 'uploads' => ['/uploads/spa-probe/x'];
        yield 'unknown admin path' => ['/admin/spa-probe-unknown'];
        yield 'unknown account path' => ['/account/spa-probe-unknown'];
        yield 'unknown register path' => ['/register/spa-probe-unknown'];
        yield 'well-known' => ['/.well-known/spa-probe'];
        yield 'profiler-style underscore namespace' => ['/_spa_probe'];
    }

    #[DataProvider('nonSpaUrls')]
    public function testServerNamespacesAreNeverAnsweredWithTheShell(string $url): void
    {
        static::assertNotSame(
            self::CATCH_ALL,
            $this->routeFor($url),
            sprintf('%s must 404, not render the SPA shell.', $url),
        );
    }

    public function testSpaPathsThatOnlyLookLikeReservedPrefixesAreStillServed(): void
    {
        // The security rule `^/account` used to match these too; the SPA owns them.
        foreach (['/accounting', '/accounts-payable', '/accounts-receivable', '/registers'] as $url) {
            static::assertSame(self::CATCH_ALL, $this->routeFor($url), sprintf('%s is a SPA path.', $url));
        }
    }

    public function testTheCatchAllIsGetOnly(): void
    {
        $this->router->getContext()->setMethod('POST');

        try {
            $this->router->match('/catalog/products');
            static::fail('POST to a client route must not be matched.');
        } catch (MethodNotAllowedException) {
            static::addToAssertionCount(1);
        }
    }

    public function testRequirementAndReservedPrefixListStayInStep(): void
    {
        foreach (HomeController::RESERVED_PREFIXES as $prefix) {
            static::assertStringContainsString(
                str_replace(search: '.', replace: '\\.', subject: $prefix),
                HomeController::SPA_PATH_REQUIREMENT,
                sprintf('"%s" is reserved but missing from SPA_PATH_REQUIREMENT.', $prefix),
            );
        }
    }

    /**
     * Expands `:param` and the `?` optional marker into concrete URLs.
     *
     * @return list<string>
     */
    private function clientUrls(): array
    {
        $file = __DIR__ . '/../../Fixtures/spa-routes.json';
        static::assertFileExists(
            $file,
            'Generate it: UPDATE_SPA_ROUTES=1 yarn test:unit assets/app/shell/spaPaths.test.ts',
        );

        try {
            /** @var array{paths: list<string>} $data */
            $data = json_decode(
                json: (string) file_get_contents($file),
                associative: true,
                depth: 512,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $e) {
            static::fail('spa-routes.json is not valid JSON: ' . $e->getMessage());
        }

        $urls = [];
        foreach ($data['paths'] as $path) {
            if (str_ends_with($path, '?')) {
                // `/credit-note/:id?` serves both `/credit-note` and `/credit-note/<id>`.
                $withoutOptional = preg_replace(pattern: '#/:[^/]+\?$#', replacement: '', subject: $path);
                $urls[] = null === $withoutOptional || '' === $withoutOptional ? '/' : $withoutOptional;
                $path = substr(string: $path, offset: 0, length: -1);
            }
            $urls[] = preg_replace(pattern: '#:[A-Za-z]+#', replacement: 'spa-probe-id', subject: $path) ?? $path;
        }

        return array_values(array_unique($urls));
    }

    private function routeFor(string $url): ?string
    {
        $this->router->getContext()->setMethod('GET');

        try {
            $match = $this->router->match($url);
        } catch (ResourceNotFoundException|MethodNotAllowedException) {
            return null;
        } catch (ExceptionInterface) {
            return null;
        }

        return \is_string($match['_route'] ?? null) ? $match['_route'] : null;
    }
}
