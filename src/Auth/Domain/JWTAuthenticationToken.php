<?php

namespace App\Auth\Domain;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class JWTAuthenticationToken extends PostAuthenticationToken
{
    private string $token;

    public function __construct(UserInterface $user, string $firewallName, array $roles, string $token)
    {
        parent::__construct($user, $firewallName, $roles);

        $this->token = $token;
    }

    public function getCredentials(): string
    {
        return $this->token;
    }
}
