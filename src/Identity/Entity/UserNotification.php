<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Identity\Repository\UserNotificationRepository;
use App\State\Notification\UserNotificationProvider;
use App\State\Notification\UserNotificationReadAllProcessor;
use App\State\Notification\UserNotificationReadProcessor;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UserNotificationRepository::class)]
#[ORM\Table(name: 'user_notification')]
#[ORM\Index(name: 'idx_user_notification_user', columns: ['user_id', 'read_at', 'created_at'])]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/user-notifications',
            normalizationContext: ['groups' => ['user_notification:read']],
            provider: UserNotificationProvider::class,
        ),
        new Patch(
            uriTemplate: '/user-notifications/{id}/read',
            normalizationContext: ['groups' => ['user_notification:read']],
            processor: UserNotificationReadProcessor::class,
        ),
        new Post(
            uriTemplate: '/user-notifications/read-all',
            input: false,
            output: false,
            processor: UserNotificationReadAllProcessor::class,
        ),
    ],
    mercure: true,
    paginationEnabled: false,
)]
class UserNotification
{
    #[Groups(['user_notification:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    /**
     * Integer FK to `usuario.usuario_id`.
     *
     * Stored as raw INT (not a ManyToOne Doctrine relation) intentionally:
     * UserNotificationService writes notifications via raw DBAL to avoid
     * flush-inside-flush issues in Doctrine lifecycle listeners. A full ORM
     * relation would force callers to hold managed User entities.
     *
     * The DB-level FK constraint is declared via `#[ORM\JoinColumn]` below
     * so that Doctrine schema-diff can generate the DDL constraint — but the
     * PHP accessor returns int, not a User object.
     *
     * @see UserNotificationService — raw DBAL writer
     * @see UserNotificationReadProcessor — reads userId for authorization checks
     */
    #[ORM\Column(name: 'user_id', type: Types::INTEGER)]
    private int $userId;

    #[Groups(['user_notification:read'])]
    #[ORM\Column(name: 'type', type: Types::STRING, length: 50)]
    #[ApiProperty(description: 'user_notification.type.label')]
    private string $type;

    #[Groups(['user_notification:read'])]
    #[ORM\Column(name: 'severity', type: Types::STRING, length: 10)]
    #[ApiProperty(description: 'user_notification.severity.label')]
    private string $severity;

    #[Groups(['user_notification:read'])]
    #[ORM\Column(name: 'title', type: Types::STRING, length: 200)]
    #[ApiProperty(description: 'user_notification.title.label')]
    private string $title;

    #[Groups(['user_notification:read'])]
    #[ORM\Column(name: 'body', type: Types::TEXT)]
    #[ApiProperty(description: 'user_notification.body.label')]
    private string $body;

    #[Groups(['user_notification:read'])]
    #[ORM\Column(name: 'action_url', type: Types::STRING, length: 500, nullable: true)]
    #[ApiProperty(description: 'user_notification.action_url.label')]
    private ?string $actionUrl = null;

    #[Groups(['user_notification:read'])]
    #[ORM\Column(name: 'read_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[ApiProperty(description: 'user_notification.read_at.label')]
    private ?DateTimeImmutable $readAt = null;

    #[Groups(['user_notification:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    #[ApiProperty(description: 'user_notification.created_at.label')]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    public function setActionUrl(?string $actionUrl): static
    {
        $this->actionUrl = $actionUrl;

        return $this;
    }

    public function getReadAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function markRead(): static
    {
        $this->readAt = new DateTimeImmutable();

        return $this;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
