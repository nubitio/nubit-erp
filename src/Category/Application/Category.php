<?php

namespace App\Category\Application;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Category
{
    public function __construct(
        public string $name,
        public string $description,
    )
    {
    }
}