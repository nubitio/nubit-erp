<?php

namespace App\Dto\RoleMenu;

class MenuDto
{
    public function __construct(
        public int $id,
        public string $text,
        /**
         * @var MenuItemDto[]
         */
        public array $items,
    ) {
    }
}
