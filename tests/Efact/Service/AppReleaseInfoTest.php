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
        $content = file_get_contents($root . '/config/app-update.json');
        static::assertIsString($content);
        $policy = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        static::assertIsArray($policy);
        $response = (new AppVersionController())(new AppReleaseInfo($root, 'test-release'));
        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        static::assertIsArray($data);

        static::assertSame('test-release', $data['version']);
        static::assertSame($policy['offlineContractVersion'], $data['offlineContractVersion']);
        static::assertSame($policy['minimumOfflineContractVersion'], $data['minimumOfflineContractVersion']);
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        static::assertArrayHasKey('revision', $data);
    }
}
