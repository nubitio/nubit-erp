<?php

namespace App\Dto\Auth;

class AuthBranchDto
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
