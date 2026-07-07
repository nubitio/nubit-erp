<?php

declare(strict_types=1);

namespace App\Settings\Dto;

class RoleMenuDto
{
    public function __construct(
        public int $id,
        public string $text,
        /**
         * @var RoleMenuItemDto[]
         */
        public array $items,
        public bool $selected = false,
    ) {}
}
