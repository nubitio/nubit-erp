<?php

declare(strict_types=1);

namespace App\Tests\FixedAssets\State;

use ApiPlatform\Metadata\Post;
use App\FixedAssets\Dto\FixedAssetDisposeDto;
use App\FixedAssets\Entity\FixedAsset;
use App\FixedAssets\Service\FixedAssetService;
use App\FixedAssets\State\FixedAssetDisposeProcessor;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class FixedAssetDisposeProcessorTest extends TestCase
{
    public function testDisposesActiveAsset(): void
    {
        $asset = new FixedAsset();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->with(FixedAsset::class, 'a1')->willReturn($asset);
        $entityManager->expects(self::once())->method('flush');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new FixedAssetDisposeProcessor($entityManager, $security, new FixedAssetService());
        $result = $processor->process(new FixedAssetDisposeDto(id: 'a1', disposalNote: 'Vendido'), new Post());

        static::assertSame(FixedAsset::DISPOSED, $result->getStatus());
        static::assertNotNull($result->getDisposedAt());
        static::assertSame('Vendido', $result->getDisposalNote());
    }

    public function testThrowsWhenNotAuthenticated(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('find');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(null);

        $processor = new FixedAssetDisposeProcessor($entityManager, $security, new FixedAssetService());

        $this->expectException(UnauthorizedHttpException::class);
        $processor->process(new FixedAssetDisposeDto(id: 'a1'), new Post());
    }

    public function testThrowsWhenAssetNotFound(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new FixedAssetDisposeProcessor($entityManager, $security, new FixedAssetService());

        $this->expectException(NotFoundHttpException::class);
        $processor->process(new FixedAssetDisposeDto(id: 'missing'), new Post());
    }
}
