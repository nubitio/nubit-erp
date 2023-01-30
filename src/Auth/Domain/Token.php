<?php

namespace App\Auth\Domain;

class Token
{
    public function __construct(
        public string $token
    )
    {
    }
}