<?php

namespace App\Dto\RoleMenu;

class RoleMenuItemDto
{
    public function __construct(
        public int $id,
        public string $text,
        public bool $selected,
    ) {
    }
}
