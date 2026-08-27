<?php

declare(strict_types=1);

namespace App\AI\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity]
#[ORM\Table(name: 'assistant_thread')]
#[ORM\Index(name: 'idx_assistant_thread_user_status', columns: ['user_id', 'status', 'updated_at'])]
class AssistantThread
{
    public const string OPEN = 'open';

    public const string CLOSED = 'closed';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[ORM\Column(name: 'user_id', type: Types::INTEGER)]
    private int $userId;

    #[ORM\Column(length: 20)]
    private string $status = self::OPEN;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, AssistantThreadMessage> */
    #[ORM\OneToMany(
        targetEntity: AssistantThreadMessage::class,
        mappedBy: 'thread',
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, AssistantThreadMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(AssistantThreadMessage $message): void
    {
        $this->messages->add($message);
        $message->setThread($this);
        $this->touch();
    }
}
