<?php

declare(strict_types=1);

namespace App\Efact\Domain;

use Nubit\ApiPlatform\Doctrine\Traits\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AuditableEntity
{
    use TimestampableTrait;

    #[ORM\Column(name: 'created_by', type: 'integer', nullable: true)]
    private ?int $createdBy = null;

    #[ORM\Column(name: 'updated_by', type: 'integer', nullable: true)]
    private ?int $updatedBy = null;

    #[ORM\Column(name: 'active', type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?int $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function activate(): static
    {
        $this->active = true;

        return $this;
    }

    public function deactivate(): static
    {
        $this->active = false;

        return $this;
    }
}
