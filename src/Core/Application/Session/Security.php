<?php

declare(strict_types=1);

namespace App\Core\Application\Session;

use App\Dto\Auth\AuthUserDto;

interface Security
{
    public function isAuthenticated(): bool;

    public function connectedUser(): ?AuthUserDto;
}
