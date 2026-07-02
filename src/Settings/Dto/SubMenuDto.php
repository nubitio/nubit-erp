<?php

declare(strict_types=1);

namespace App\Settings\Dto;

class SubMenuDto
{
    public function __construct(
        public string $text,
        public string $path,
    ) {}
}
