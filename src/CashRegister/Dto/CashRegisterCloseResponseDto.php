<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class CashRegisterCloseResponseDto
{
    public function __construct(
        #[Groups(['cash_register_close:read'])]
        public bool $success,
        #[Groups(['cash_register_close:read'])]
        public string $message,
        #[Groups(['cash_register_close:read'])]
        public mixed $data = null,
    ) {}
}
