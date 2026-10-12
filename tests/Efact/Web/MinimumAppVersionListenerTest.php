<?php

declare(strict_types=1);

namespace App\Tests\Efact\Web;

use App\Efact\Service\AppReleaseInfo;
use App\Efact\Web\MinimumAppVersionListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class MinimumAppVersionListenerTest extends TestCase
{
    #[DataProvider('requests')]
    public function testMinimumVersionGuard(
        string $path,
        string $method,
        ?string $version,
        bool $csrf,
        int $expected,
    ): void {
        $root = sys_get_temp_dir() . '/nubit-release-' . bin2hex(random_bytes(8));
        mkdir($root . '/config', recursive: true);
        file_put_contents($root . '/config/app-update.json', json_encode([
            'offlineContractVersion' => 2,
            'minimumOfflineContractVersion' => 2,
        ], flags: JSON_THROW_ON_ERROR));
        try {
            $request = Request::create($path, method: $method);
            if ($version !== null) {
                $request->headers->set('X-App-Contract-Version', $version);
            }
            if ($csrf) {
                $request->headers->set('X-CSRF-Token', bin2hex(random_bytes(16)));
            }
            $event = new ControllerEvent(
                $this->createStub(HttpKernelInterface::class),
                static fn(): JsonResponse => new JsonResponse(status: 200),
                $request,
                HttpKernelInterface::MAIN_REQUEST,
            );
            (new MinimumAppVersionListener(new AppReleaseInfo($root)))($event);
            $response = $event->getController()();
            static::assertInstanceOf(JsonResponse::class, $response);
            static::assertSame($expected, $response->getStatusCode());
            if ($expected === 426) {
                $content = $response->getContent();
                static::assertIsString($content);
                static::assertStringContainsString('APP_UPDATE_REQUIRED', $content);
            }
        } finally {
            unlink($root . '/config/app-update.json');
            rmdir($root . '/config');
            rmdir($root);
        }
    }

    /** @return iterable<string, array{string, string, ?string, bool, int}> */
    public static function requests(): iterable
    {
        yield 'obsolete browser' => ['/api/products/1', 'PATCH', '1', false, 426];
        yield 'current browser' => ['/api/products/1', 'PATCH', '2', false, 200];
        yield 'legacy csrf browser' => ['/api/sales', 'POST', null, true, 426];
        yield 'machine integration' => ['/api/v1/documents', 'POST', null, false, 200];
        yield 'auth remains available' => ['/api/auth/logout', 'POST', '1', false, 200];
        yield 'control plane remains independent' => ['/api/control-plane/tenants', 'POST', null, true, 200];
        yield 'read remains available' => ['/api/products', 'GET', '1', false, 200];
    }
}
