<?php

declare(strict_types=1);

namespace App\Efact\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponse extends JsonResponse
{
    /** @var mixed The application-level payload (not the JsonResponse internal $data property). */
    private mixed $payload;

    public function __construct(
        public bool $success,
        public string $message,
        mixed $payload = null,
    ) {
        $this->payload = $payload;
        parent::__construct($this->toArray(), $success ? 200 : 400);
    }

    public static function success(string $message = 'Success', mixed $data = null): self
    {
        return new self(true, $message, $data);
    }

    public static function error(string $message, mixed $data = null): self
    {
        return new self(false, $message, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data'    => $this->payload,
        ];
    }
}
