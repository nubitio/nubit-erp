<?php

namespace App\Media\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Media\Application\MediaController;
use App\Media\Domain\Media;

#[ApiResource(
    shortName: 'Media',
    operations: [
        new Get(),
        new Post(
            controller: MediaController::class,
            openapiContext: [
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            deserialize: false,
        ),
        new Delete()
    ],
    provider: MediaProvider::class,
    processor: MediaProcessor::class
)]
class MediaDto extends Media
{
    #[ApiProperty(identifier: true)]
    public ?string $id;
}