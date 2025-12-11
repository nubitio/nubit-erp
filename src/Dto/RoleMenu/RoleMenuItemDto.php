<?php

declare(strict_types=1);

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
