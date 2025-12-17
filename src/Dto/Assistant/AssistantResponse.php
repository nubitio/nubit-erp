<?php

declare(strict_types=1);

namespace App\Dto\Assistant;

class AssistantResponse
{
    public function __construct(
        public readonly string $message,
    ) {
    }
}
