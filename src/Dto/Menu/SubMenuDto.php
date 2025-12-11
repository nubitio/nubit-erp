<?php

declare(strict_types=1);

namespace App\Dto\Menu;

class SubMenuDto
{
    public function __construct(
        public string $text,
        public string $path,
    ) {
    }
}
