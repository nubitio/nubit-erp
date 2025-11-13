<?php

declare(strict_types=1);

namespace App\Core\Http;

readonly class ApiResponse
{
    public function __construct(
        public bool $success,
        public string $message,
        public mixed $data = null,
    ) {
    }

    public static function success(string $message = 'Success', mixed $data = null): self
    {
        return new self(true, $message, $data);
    }

    public static function error(string $message, mixed $data = null): self
    {
        return new self(false, $message, $data);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
