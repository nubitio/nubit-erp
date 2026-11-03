<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use App\Sales\Reminder\ReminderChannel;
use App\Sales\Reminder\ReminderStatus;
use App\Sales\Reminder\ReminderTone;
use App\Sales\Repository\CollectionReminderRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record of a payment reminder prepared or sent for an overdue sale. Kept for twelve
 * months (see app:receivables:purge-reminders); it holds the recipient's phone/email and
 * the message text, so it is only exposed to users with receivables.remind.
 */
#[ORM\Entity(repositoryClass: CollectionReminderRepository::class)]
#[ORM\Table(name: 'sales_collection_reminder')]
#[ORM\Index(name: 'idx_sales_collection_reminder_sale_channel', columns: ['sale_record_id', 'channel', 'created_at'])]
#[ORM\Index(name: 'idx_sales_collection_reminder_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_sales_collection_reminder_created', columns: ['created_at'])]
class CollectionReminder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(name: 'sale_record_id', length: 36)]
        private string $saleRecordId,
        #[ORM\Column(name: 'customer_id', type: 'integer')]
        private int $customerId,
        #[ORM\Column(length: 16, enumType: ReminderChannel::class)]
        private ReminderChannel $channel,
        #[ORM\Column(length: 16, enumType: ReminderTone::class)]
        private ReminderTone $tone,
        #[ORM\Column(length: 16, enumType: ReminderStatus::class)]
        private ReminderStatus $status,
        #[ORM\Column(type: 'text')]
        private string $body,
        #[ORM\Column(name: 'recipient_phone', length: 32, nullable: true)]
        private ?string $recipientPhone,
        #[ORM\Column(name: 'recipient_email', length: 255, nullable: true)]
        private ?string $recipientEmail,
        #[ORM\Column(name: 'failure_reason', length: 255, nullable: true)]
        private ?string $failureReason,
        #[ORM\Column(name: 'created_by_user_id', type: 'integer', nullable: true)]
        private ?int $createdByUserId,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaleRecordId(): string
    {
        return $this->saleRecordId;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function getChannel(): ReminderChannel
    {
        return $this->channel;
    }

    public function getTone(): ReminderTone
    {
        return $this->tone;
    }

    public function getStatus(): ReminderStatus
    {
        return $this->status;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getRecipientPhone(): ?string
    {
        return $this->recipientPhone;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getCreatedByUserId(): ?int
    {
        return $this->createdByUserId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
