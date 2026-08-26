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
use App\Identity\Repository\RoleRepository;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'rol')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('perm:roles.view')"),
        new Get(security: "is_granted('perm:roles.view')"),
        new Post(security: "is_granted('perm:roles.edit')"),
        new Patch(security: "is_granted('perm:roles.edit') and (object.getId() > 1 or is_granted('ROLE_SUPER_ADMIN'))"),
        new Delete(
            security: "is_granted('perm:roles.edit') and (object.getId() > 1 or is_granted('ROLE_SUPER_ADMIN'))",
        ),
    ],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class Role
{
    #[Groups(['user:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'rol_id')]
    private ?int $id = null;

    #[Groups(['user:read'])]
    #[ApiProperty(description: 'role.name.label')]
    #[ORM\Column(name: 'descripcion')]
    private string $name;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
