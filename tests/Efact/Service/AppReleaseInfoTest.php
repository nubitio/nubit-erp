<?php

declare(strict_types=1);

namespace App\Tests\Efact\Service;

use App\Efact\Controller\AppVersionController;
use App\Efact\Service\AppReleaseInfo;
use PHPUnit\Framework\TestCase;

final class AppReleaseInfoTest extends TestCase
{
    public function testVersionEndpointIsUncachedAndUsesTheSharedContractPolicy(): void
    {
        $root = dirname(__DIR__, levels: 3);
        $policy = json_decode(
            file_get_contents($root . '/config/app-update.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
        $response = (new AppVersionController())(new AppReleaseInfo($root, 'test-release'));
        $data = json_decode($response->getContent(), associative: true, flags: JSON_THROW_ON_ERROR);

        static::assertSame('test-release', $data['version']);
        static::assertSame($policy['offlineContractVersion'], $data['offlineContractVersion']);
        static::assertSame($policy['minimumOfflineContractVersion'], $data['minimumOfflineContractVersion']);
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        static::assertArrayHasKey('revision', $data);
    }
}
