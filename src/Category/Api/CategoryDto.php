<?php

namespace App\Category\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\Category\Domain\Category;

class CategoryDto extends Category
{
    #[ApiProperty(identifier: true)]
    public ?string $categoria_id;
}
