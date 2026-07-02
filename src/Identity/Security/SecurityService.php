<?php

declare(strict_types=1);

namespace App\Identity\Security;

use App\Identity\Dto\AuthUserDto;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use Nubit\AdminBundle\Auth\JWTAuthenticationToken;
use Nubit\AdminBundle\Auth\JWTManager;
use Nubit\AdminBundle\Auth\JWTManagerInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;

final readonly class SecurityService implements Security
{
    public function __construct(
        private SecurityComponent $security,
        private JWTManagerInterface $jwtManager,
    ) {}

    #[Override]
    public function isAuthenticated(): bool
    {
        return $this->connectedUser() instanceof AuthUserDto;
    }

    #[Override]
    public function connectedUser(): ?AuthUserDto
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

        return AuthUserDto::createFromJWTPayload($jwtPayload);
    }
}
