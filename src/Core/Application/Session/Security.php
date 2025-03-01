<?php

namespace App\Core\Application\Session;

use App\Dto\Auth\AuthUserDTO;

interface Security
{
    public function isAuthenticated(): bool;

    public function connectedUser(): ?AuthUserDTO;
}
