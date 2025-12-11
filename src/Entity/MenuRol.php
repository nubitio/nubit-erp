<?php

namespace App\Entity;

use App\Repository\MenuRolRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuRolRepository::class)]
class MenuRol
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Menu $menu;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(referencedColumnName: 'rol_id', nullable: false)]
    private Role $role;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenu(): Menu
    {
        return $this->menu;
    }

    public function setMenu(Menu $menu): static
    {
        $this->menu = $menu;

        return $this;
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
}
