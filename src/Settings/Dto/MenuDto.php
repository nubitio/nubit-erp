<?php

declare(strict_types=1);

namespace App\Settings\Dto;

class MenuDto
{
    public function __construct(
        public string $text,
        public string $icon,
        public string $path,
        /** @var array<int, SubMenuDto> */
        public array $items = [],
    ) {
    }
}
