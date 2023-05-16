<?php

namespace App\Auth\Api;

use App\Auth\Domain\Token;

class TokenDto extends Token
{
    public string $token;
}
