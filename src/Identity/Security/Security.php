<?php

declare(strict_types=1);

namespace App\Identity\Security;

use App\Identity\Dto\AuthUserDto;

interface Security
{
    public function isAuthenticated(): bool;

    public function connectedUser(): ?AuthUserDto;
}
