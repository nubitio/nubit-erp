<?php

namespace App\Category\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Category\Domain\Category;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ApiResource(
    shortName: 'Category',
    provider: CategoryProvider::class,
    processor: CategoryProcessor::class,
)]
class CategoryDto extends Category
{
    #[ApiProperty(identifier: true)]
    public ?string $categoria_id;
    #[Ignore]
    public ?Category $parent;
}