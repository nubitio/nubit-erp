<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Override;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\UserRepository;
use App\State\User\UserSoftDeleteProcessor;
use App\State\User\UserPasswordHasher;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'usuario')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(validationContext: ['groups' => ['Default', 'user:create']], processor: UserPasswordHasher::class),
        new Get(),
        new Put(processor: UserPasswordHasher::class),
        new Patch(processor: UserPasswordHasher::class),
        new Delete(processor: UserSoftDeleteProcessor::class, output: false),
    ],
    normalizationContext: [
        'groups' => ['user:read'],
    ],
    denormalizationContext: [
        'groups' => ['user:create', 'user:update'],
    ],
    filters: [
        DevExtremeFilter::class,
    ],
    mercure: true,
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[Groups(['user:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'usuario_id')]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Groups([
        'user:read',
        'user:create',
        'user:update',
    ])]
    #[ApiProperty(description: 'user.username.label')]
    #[ORM\Column(length: 255)]
    private string $username;

    #[ORM\Column(length: 255)]
    private string $password;

    #[Groups([
        'user:create',
        'user:update',
    ])]
    #[Assert\NotBlank(groups: ['user:create'])]
    #[Assert\Length(min: 6, max: 255)]
    #[ApiProperty(description: 'user.plain_password.label')]
    private ?string $plainPassword = null;

    #[Groups([
        'user:read',
        'user:create',
        'user:update',
    ])]
    #[ApiProperty(description: 'user.role.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'rol_id', referencedColumnName: 'rol_id', nullable: false)]
    private Role $role;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true, options: ['default' => null])]
    private ?DateTimeImmutable $deletedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    #[Override]
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /** @return list<string> */
    #[Override]
    public function getRoles(): array
    {
        $roles = match ($this->getRole()?->getId()) {
            1 => ['ROLE_SUPER_ADMIN'],
            2 => ['ROLE_ADMIN'],
            3 => ['ROLE_CASHIER'],
            default => [],
        };
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    #[Override]
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    #[Override]
    public function isEqualTo(UserInterface $user): bool
    {
        return $this->getUserIdentifier() === $user->getUserIdentifier();
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }
}
