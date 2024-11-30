<?php

namespace App\Consult\Domain;

class Consult
{
    public function __construct(
        public string $id,
        public string $name,
        public string $address,
        public bool $active,
    ) {
    }
}
