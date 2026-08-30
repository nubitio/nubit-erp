<?php

declare(strict_types=1);

namespace App\Integration\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The authenticated identity behind an `/api/v1` request.
 *
 * Not a person: it is an {@see \App\Integration\Entity\ApiClient}. Its scopes
 * are exposed as roles (`documents:write` -> `ROLE_SCOPE_DOCUMENTS_WRITE`) plus
 * a blanket `ROLE_INTEGRATION`, so `#[IsGranted]` works unchanged.
 */
final class ApiClientUser implements UserInterface
{
    /** @var list<string> */
    private array $roles;

    /**
     * @param list<string> $scopes
     */
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientName,
        private readonly string $tenant,
        private readonly array $scopes,
    ) {
        $this->roles = ['ROLE_INTEGRATION'];
        foreach ($scopes as $scope) {
            $this->roles[] = self::roleForScope($scope);
        }
    }

    public static function roleForScope(string $scope): string
    {
        return 'ROLE_SCOPE_' . strtoupper(str_replace([':', '-', '.'], replace: '_', subject: $scope));
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    public function getTenant(): string
    {
        return $this->tenant;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getUserIdentifier(): string
    {
        return 'api-client:' . $this->clientId;
    }

    public function eraseCredentials(): void {}
}
