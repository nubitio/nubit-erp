<?php

namespace App\Core\Common;

readonly class ApiResponse
{
    public function __construct(
        public bool $success,
        public string $message,
        public mixed $data = null,
    ) {
    }
}
