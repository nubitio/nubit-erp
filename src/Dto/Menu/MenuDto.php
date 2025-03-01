<?php

namespace App\Dto\Menu;

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
