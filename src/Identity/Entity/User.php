<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Efact\Validator as AppAssert;
use App\Identity\Entity\Role;
use App\Identity\Repository\UserRepository;
use App\Identity\State\UserPasswordHasher;
use App\Identity\State\UserSoftDeleteProcessor;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Attribute\SoftDeletable;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Nubit\TenantBundle\Attribute\QuotaResource;
use Override;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[AppAssert\UniqueEntity(fields: ['email'], message: 'Ya existe un usuario con este correo.')]
#[SoftDeletable]
#[QuotaResource('users')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'usuario')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('perm:users.view')"),
        new Post(
            validationContext: ['groups' => ['Default', 'user:create']],
            processor: UserPasswordHasher::class,
            security: "is_granted('perm:users.create') and is_granted('perm:roles.assign')",
            securityPostDenormalize: "object.getRole().getId() > 1 or is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Get(security: "is_granted('perm:users.view')"),
        new Put(
            processor: UserPasswordHasher::class,
            security: "is_granted('perm:users.edit')",
            securityPostDenormalize: "(object.getRole() == previous_object.getRole() or is_granted('perm:roles.assign')) and (object.getRole().getId() > 1 or is_granted('ROLE_SUPER_ADMIN'))",
        ),
        new Patch(
            processor: UserPasswordHasher::class,
            security: "is_granted('perm:users.edit')",
            securityPostDenormalize: "(object.getRole() == previous_object.getRole() or is_granted('perm:roles.assign')) and (object.getRole().getId() > 1 or is_granted('ROLE_SUPER_ADMIN'))",
        ),
        new Delete(
            processor: UserSoftDeleteProcessor::class,
            output: false,
            security: "is_granted('perm:users.delete')",
        ),
    ],
    normalizationContext: [
        'groups' => ['user:read'],
    ],
    denormalizationContext: [
        'groups' => ['user:create', 'user:update'],
    ],
    filters: [
        DataGridFilter::class,
    ],
    mercure: true,
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[Groups(['user:read', 'expense_report:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'usuario_id')]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Groups([
        'user:read',
        'user:create',
        'user:update',
        'expense_report:read',
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

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    #[ApiProperty(description: 'user.email.label')]
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[Groups(['user:read'])]
    #[ApiProperty(description: 'user.last_login_at.label', writable: false)]
    #[ORM\Column(name: 'last_login_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastLoginAt = null;

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
        if ($this->username === '') {
            throw new \LogicException('The username cannot be empty.');
        }

        return $this->username;
    }

    /** @return list<string> */
    #[Override]
    public function getRoles(): array
    {
        $roles = match ($this->getRole()?->getId()) {
            // 0 is the legacy root superuser (see MenuProvider::getMenuForRole)
            0, 1 => ['ROLE_SUPER_ADMIN'],
            2 => ['ROLE_ADMIN'],
            3 => ['ROLE_CASHIER'],
            default => [],
        };
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
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

    public function setRole(Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $email = $email === null ? null : trim($email);
        $this->email = $email === '' ? null : $email;

        return $this;
    }

    public function getLastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
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
