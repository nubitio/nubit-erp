<?php

declare(strict_types=1);

namespace App\Tests\State\Media;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\State\ProcessorInterface;
use App\Media\Entity\Media;
use App\Media\State\MediaSoftDeleteProcessor;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MediaSoftDeleteProcessorTest extends TestCase
{
    /** @var ProcessorInterface<Media, null>&MockObject */
    private ProcessorInterface&MockObject $persistProcessor;

    public function testSoftDeleteSetsDeletedAtAndPersistsMedia(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $media = (new Media())->setPath('logo.png');

        $this->persistProcessor
            ->expects(self::once())
            ->method('process')
            ->with(
                self::callback(static function (Media $processedMedia): bool {
                    return $processedMedia->getDeletedAt() instanceof DateTimeImmutable;
                }),
                self::isInstanceOf(Delete::class),
                ['id' => 'media-1'],
                ['request_uri' => '/api/media/media-1'],
            );

        $processor = new MediaSoftDeleteProcessor($this->persistProcessor);

        $processor->process($media, new Delete(), ['id' => 'media-1'], ['request_uri' => '/api/media/media-1']);
        self::assertInstanceOf(DateTimeImmutable::class, $media->getDeletedAt());
    }

    public function testSoftDeleteKeepsExistingDeletedAtTimestamp(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $deletedAt = new DateTimeImmutable('-1 day');
        $media = (new Media())
            ->setPath('logo.png')
            ->setDeletedAt($deletedAt);

        $this->persistProcessor
            ->expects(self::once())
            ->method('process')
            ->with($media, self::isInstanceOf(Delete::class), [], []);

        $processor = new MediaSoftDeleteProcessor($this->persistProcessor);

        $processor->process($media, new Delete(), [], []);

        self::assertSame($deletedAt, $media->getDeletedAt());
    }

    public function testSoftDeleteLeavesStoredFilePathAndPhysicalFileIntact(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $filePath = tempnam(sys_get_temp_dir(), 'media-soft-delete-');
        self::assertNotFalse($filePath);
        file_put_contents($filePath, 'keep-me');

        $media = (new Media())->setPath($filePath);

        $this->persistProcessor
            ->expects(self::once())
            ->method('process')
            ->with($media, self::isInstanceOf(Delete::class), ['id' => 'media-1'], []);

        $processor = new MediaSoftDeleteProcessor($this->persistProcessor);

        try {
            $processor->process($media, new Delete(), ['id' => 'media-1'], []);

            self::assertInstanceOf(DateTimeImmutable::class, $media->getDeletedAt());
            self::assertSame($filePath, $media->getPath());
            self::assertFileExists($filePath);
        } finally {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }
    }

    public function testSoftDeleteIsIdempotentAcrossRepeatedDeleteRequests(): void
    {
        $this->persistProcessor = $this->createMock(ProcessorInterface::class);
        $media = (new Media())->setPath('logo.png');

        $this->persistProcessor
            ->expects(self::exactly(2))
            ->method('process')
            ->with($media, self::isInstanceOf(Delete::class), ['id' => 'media-1'], []);

        $processor = new MediaSoftDeleteProcessor($this->persistProcessor);

        $processor->process($media, new Delete(), ['id' => 'media-1'], []);
        $firstDeletedAt = $media->getDeletedAt();
        $processor->process($media, new Delete(), ['id' => 'media-1'], []);

        self::assertInstanceOf(DateTimeImmutable::class, $firstDeletedAt);
        self::assertSame($firstDeletedAt, $media->getDeletedAt());
    }

    public function testMediaDeleteOperationUsesSoftDeleteProcessorAndNoOutput(): void
    {
        $resource = (new ReflectionClass(Media::class))->getAttributes(ApiResource::class)[0]->newInstance();

        foreach ($resource->getOperations() ?? [] as $operation) {
            if (!$operation instanceof Delete) {
                continue;
            }

            self::assertSame(MediaSoftDeleteProcessor::class, $operation->getProcessor());
            self::assertFalse($operation->getOutput());

            return;
        }

        self::fail('Media ApiResource is missing its DELETE operation.');
    }
}
