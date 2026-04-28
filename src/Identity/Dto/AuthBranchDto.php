<?php

namespace App\Identity\Dto;

class AuthBranchDto
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
