<?php

namespace App\Core\Domain\Common;

readonly class ApiResponse
{
    public function __construct(
        public bool $success,
        public string $message,
        public mixed $data = null,
    ) {
    }
}
