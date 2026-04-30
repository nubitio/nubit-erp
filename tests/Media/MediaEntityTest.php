<?php

declare(strict_types=1);

namespace App\Tests\Media;

use App\Media\Entity\Media;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MediaEntityTest extends TestCase
{
    public function testMediaDefaultValues(): void
    {
        $media = new Media();

        self::assertNull($media->getId());
        self::assertNull($media->getDeletedAt());
    }

    public function testMediaSetPath(): void
    {
        $media = new Media();
        $result = $media->setPath('tenant/42/images/logo.png');

        self::assertSame('tenant/42/images/logo.png', $media->getPath());
        self::assertSame($media, $result); // fluent
    }

    public function testMediaSoftDelete(): void
    {
        $media = new Media();
        $deletedAt = new DateTimeImmutable('2026-04-30 10:00:00');

        $result = $media->setDeletedAt($deletedAt);

        self::assertSame($deletedAt, $media->getDeletedAt());
        self::assertSame($media, $result); // fluent
    }

    public function testMediaRestoreFromSoftDelete(): void
    {
        $media = new Media();
        $media->setDeletedAt(new DateTimeImmutable());
        $media->setDeletedAt(null);

        self::assertNull($media->getDeletedAt());
    }
}
