<?php

namespace App\Core\Infrastructure\Symfony\Security;

use App\Auth\Api\AuthUserDTO;
use App\Core\Application\Session\Security;
use App\Core\Presentation\Security\JWTAuthenticationToken;
use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;

final readonly class SecurityService implements Security
{
    public function __construct(
        private SecurityComponent $security,
    ) {
    }

    public function isAuthenticated(): bool
    {
        return null !== $this->connectedUser();
    }

    public function connectedUser(): ?AuthUserDTO
    {
        $authenticatedUser = $this->security->getUser();

        if (!$authenticatedUser instanceof User) {
            return null;
        }

        $token = $this->security->getToken();

        if (!$token instanceof JWTAuthenticationToken) {
            return null;
        }

        $jwtPayload = (array) JWT::decode(
            $token->getCredentials(),
            new Key($_ENV['APP_SECRET'], 'HS256')
        );

        return AuthUserDTO::createFromJWTPayload($jwtPayload);
    }
}
