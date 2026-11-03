<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/** Sends a reminder e-mail and reports the outcome instead of throwing, so it can be recorded. */
final readonly class ReminderEmailSender
{
    private const int MAX_REASON_LENGTH = 255;

    public function __construct(
        private MailerInterface $mailer,
        private string $mailerFrom,
    ) {}

    /**
     * @return array{0: ReminderStatus, 1: ?string} status and, when it failed, a short reason
     */
    public function send(string $to, string $subject, string $text): array
    {
        try {
            $this->mailer->send(
                new Email()
                    ->from($this->mailerFrom)
                    ->to($to)
                    ->subject($subject)
                    ->text($text),
            );
        } catch (TransportExceptionInterface $exception) {
            $reason = preg_replace('/\s+/', ' ', $exception->getMessage()) ?? '';

            return [
                ReminderStatus::Failed,
                mb_substr('No se pudo enviar el correo: ' . $reason, 0, self::MAX_REASON_LENGTH),
            ];
        }

        return [ReminderStatus::Sent, null];
    }
}
