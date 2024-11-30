<?php

namespace App\Menu\Api;

class SubMenu
{
    public function __construct(
        public string $text,
        public string $path,
    ) {
    }
}
