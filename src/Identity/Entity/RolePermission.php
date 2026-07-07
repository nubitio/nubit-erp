<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Repository\RolePermissionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RolePermissionRepository::class)]
#[ORM\Table(name: 'role_permission')]
#[ORM\UniqueConstraint(name: 'uniq_role_permission', columns: ['role_id', 'permission'])]
class RolePermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'rol_id', nullable: false, onDelete: 'CASCADE')]
    private Role $role;

    #[ORM\Column(length: 100)]
    private string $permission;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getPermission(): string
    {
        return $this->permission;
    }

    public function setPermission(string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }
}
