<?php

namespace App\Dto\Menu;

class SubMenuDto
{
    public function __construct(
        public string $text,
        public string $path,
    ) {
    }
}
