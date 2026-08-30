<?php

declare(strict_types=1);

namespace App\Integration\Entity;

use App\Integration\Repository\ApiClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/**
 * A machine consumer of the tenant's public integration API (`/api/v1`).
 *
 * One client owns one or more {@see ApiKey}s (so a key can be rotated without
 * downtime) and a fixed set of scopes. The client — and its keys — belong to
 * exactly one tenant; the tenant is not carried on the row because these tables
 * already live in that tenant's database.
 */
#[ORM\Entity(repositoryClass: ApiClientRepository::class)]
#[ORM\Table(name: 'integration_api_client')]
class ApiClient
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column]
    private ?string $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $scopes = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, ApiKey> */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: ApiKey::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $keys;

    /**
     * @param list<string> $scopes
     */
    public function __construct(string $name, array $scopes)
    {
        $this->name = $name;
        $this->scopes = array_values(array_unique($scopes));
        $this->createdAt = new \DateTimeImmutable();
        $this->keys = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, ApiKey> */
    public function getKeys(): Collection
    {
        return $this->keys;
    }

    public function addKey(ApiKey $key): void
    {
        if (!$this->keys->contains($key)) {
            $this->keys->add($key);
        }
    }
}
