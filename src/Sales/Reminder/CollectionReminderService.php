<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

use App\Identity\Security\Security;
use App\Sales\Entity\CollectionReminder;
use App\Sales\Repository\CollectionReminderRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Manual payment reminders for overdue sales: preview the message, then record it
 * (and, for e-mail, send it). WhatsApp is never sent from the server; the cashier opens
 * the prepared wa.me link, so that reminder is recorded as "prepared".
 */
final readonly class CollectionReminderService
{
    /** At most one reminder per sale and channel within this window, unless forced. */
    private const string MIN_INTERVAL = '-24 hours';

    private const int MAX_BODY_LENGTH = 2000;

    private const int MAX_HISTORY = 50;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CollectionReminderRepository $reminders,
        private ReminderSaleGuard $saleGuard,
        private ReminderRecipientResolver $recipients,
        private ReminderMessageComposer $composer,
        private ReminderContextFactory $contexts,
        private ReminderEmailSender $emails,
        private Security $security,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(string $saleId, ReminderChannel $channel, ReminderTone $tone): array
    {
        $sale = $this->saleGuard->collectable($saleId);
        $recipient = $this->recipients->resolve($sale, $channel);
        $context = $this->contexts->create($sale);
        $body = $this->composer->body($context, $tone);
        $last = $this->reminders->findLast($saleId);

        return [
            'recipient' => [
                'name' => $context->contactName,
                'phone' => $recipient->phone,
                'email' => $recipient->email,
                'source' => 'customer',
            ],
            'subject' => $channel === ReminderChannel::Email ? $this->composer->subject($context) : null,
            'body' => $body,
            'documentUrl' => $context->documentUrl,
            'daysOverdue' => $context->daysOverdue,
            'whatsappUrl' => $channel === ReminderChannel::Whatsapp
                ? self::whatsappUrl((string) $recipient->phone, $body)
                : null,
            'lastReminder' => $last === null
                ? null
                : [
                    'channel' => $last->getChannel()->value,
                    'status' => $last->getStatus()->value,
                    'createdAt' => $last->getCreatedAt()->format(DATE_ATOM),
                ],
        ];
    }

    public function record(
        string $saleId,
        ReminderChannel $channel,
        ReminderTone $tone,
        ?string $body,
        bool $force,
    ): ReminderOutcome {
        $sale = $this->saleGuard->collectable($saleId);
        $recipient = $this->recipients->resolve($sale, $channel);
        $now = DateTimeImmutable::createFromInterface($this->clock->now());

        $last = $this->reminders->findLast($saleId, $channel);
        if (!$force && $last !== null && $last->getCreatedAt() > $now->modify(self::MIN_INTERVAL)) {
            throw ReminderException::tooFrequent($last->getCreatedAt());
        }

        $context = $this->contexts->create($sale);
        $text = $this->resolveBody($body, $context, $tone);
        $status = ReminderStatus::Prepared;
        $failure = null;

        if ($channel === ReminderChannel::Email) {
            [$status, $failure] = $this->emails->send(
                (string) $recipient->email,
                $this->composer->subject($context),
                $text,
            );
        }

        $customer = $sale->getCustomer();
        $reminder = new CollectionReminder(
            saleRecordId: $saleId,
            customerId: (int) $customer?->getId(),
            channel: $channel,
            tone: $tone,
            status: $status,
            body: $text,
            recipientPhone: $recipient->phone,
            recipientEmail: $recipient->email,
            failureReason: $failure,
            createdByUserId: $this->security->connectedUser()?->userId,
            createdAt: $now,
        );
        $this->entityManager->persist($reminder);
        $this->entityManager->flush();

        return new ReminderOutcome(
            $reminder,
            $channel === ReminderChannel::Whatsapp ? self::whatsappUrl((string) $recipient->phone, $text) : null,
        );
    }

    /**
     * @return list<CollectionReminder>
     */
    public function history(?string $saleId, ?int $customerId): array
    {
        if ($saleId === null && $customerId === null) {
            throw ReminderException::invalid('Indica la venta o el cliente.');
        }
        if ($saleId !== null) {
            // Confirms the sale exists in this branch before revealing its history.
            $this->saleGuard->inBranch($saleId);
        }

        return $this->reminders->findHistory($saleId, $customerId, self::MAX_HISTORY);
    }

    public static function whatsappUrl(string $number, string $text): string
    {
        return sprintf('https://wa.me/%s?text=%s', $number, rawurlencode($text));
    }

    private function resolveBody(?string $body, ReminderContext $context, ReminderTone $tone): string
    {
        if ($body === null) {
            return $this->composer->body($context, $tone);
        }
        $body = trim($body);
        if ($body === '') {
            throw ReminderException::invalid('El mensaje no puede estar vacío.');
        }
        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw ReminderException::invalid(sprintf(
                'El mensaje no puede superar %d caracteres.',
                self::MAX_BODY_LENGTH,
            ));
        }

        return $body;
    }
}
