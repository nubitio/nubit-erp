<?php

declare(strict_types=1);

namespace App\Core\Traits;

use DateTimeInterface;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

trait TimestampableTrait
{
    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private ?DateTimeInterface $updatedAt = null;

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function updateTimestampsOnPersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new DateTime();
        }

        if ($this->updatedAt === null) {
            $this->updatedAt = new DateTime();
        }
    }

    #[ORM\PreUpdate]
    public function updateTimestampsOnUpdate(): void
    {
        $this->updatedAt = new DateTime();
    }
}
