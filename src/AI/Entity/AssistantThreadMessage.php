<?php

declare(strict_types=1);

namespace App\AI\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity]
#[ORM\Table(name: 'assistant_thread_message')]
#[ORM\Index(name: 'idx_assistant_message_thread_created', columns: ['thread_id', 'created_at'])]
class AssistantThreadMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: AssistantThread::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'thread_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AssistantThread $thread;

    #[ORM\Column(length: 20)]
    private string $role;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    /** @var list<array<string, mixed>>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $actions = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getThread(): AssistantThread
    {
        return $this->thread;
    }

    public function setThread(AssistantThread $thread): void
    {
        $this->thread = $thread;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function getActions(): ?array
    {
        return $this->actions;
    }

    /**
     * @param list<array<string, mixed>>|null $actions
     */
    public function setActions(?array $actions): void
    {
        $this->actions = $actions;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
