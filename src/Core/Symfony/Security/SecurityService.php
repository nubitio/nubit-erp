<?php

namespace App\Core\Symfony\Security;

use Override;
use App\Core\Application\Session\Security;
use App\Core\Security\Auth\JWTAuthenticationToken;
use App\Core\Security\Auth\JWTManager;
use App\Dto\Auth\AuthUserDTO;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;

final readonly class SecurityService implements Security
{
    public function __construct(
        private SecurityComponent $security,
        private JWTManager $jwtManager,
    ) {
    }

    #[Override]
    public function isAuthenticated(): bool
    {
        return $this->connectedUser() instanceof AuthUserDTO;
    }

    #[Override]
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

        $jwtPayload = $this->jwtManager->decode($token->getCredentials());

        return AuthUserDTO::createFromJWTPayload($jwtPayload);
    }
}
