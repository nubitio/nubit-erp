<?php

declare(strict_types=1);

namespace App\FixedAssets\Dto;

class FixedAssetDisposeDto
{
    public function __construct(
        public string $id,
        public ?string $disposalNote = null,
    ) {}
}
