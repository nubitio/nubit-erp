<?php

declare(strict_types=1);

namespace App\Media\Entity;

use ArrayObject;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Media\Controller\MediaController;
use App\Media\Repository\MediaRepository;
use App\Media\State\MediaSoftDeleteProcessor;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/media/{id}',
        ),
        new Post(
            uriTemplate: '/media',
            controller: MediaController::class,
            openapi: new Operation(
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                    required: true,
                ),
            ),
            deserialize: false,
        ),
        new Delete(
            uriTemplate: '/media/{id}',
            processor: MediaSoftDeleteProcessor::class,
            output: false,
        ),
    ],
    mercure: true,
)]
class Media
{
    #[Groups([
        'company:read',
        'product:read',
    ])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column]
    private ?string $id = null;

    #[Groups([
        'company:read',
        'product:read',
    ])]
    #[ApiProperty(description: 'media.path.label')]
    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['default' => null])]
    private ?DateTimeImmutable $deletedAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }
}
