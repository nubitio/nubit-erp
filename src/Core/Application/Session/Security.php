<?php

namespace App\Core\Application\Session;

use App\Auth\Api\AuthUserDTO;

interface Security
{
    public function isAuthenticated(): bool;

    public function connectedUser(): ?AuthUserDTO;
}
