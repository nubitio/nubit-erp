<?php

declare(strict_types=1);

namespace App\Identity\Activity;

use App\Identity\Entity\User;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

final readonly class ActivityRecorder implements ActivityRecorderInterface
{
    /** Failed sign-ins are attacker-controlled input: cap what one address can write per minute. */
    private const int MAX_FAILED_LOGINS_PER_IP_PER_MINUTE = 10;

    public function __construct(
        private Connection $connection,
        private Security $security,
        private RequestStack $requests,
        private LoggerInterface $logger,
    ) {}

    #[Override]
    public function record(
        string $type,
        string $summary,
        ?string $detail = null,
        ?string $subject = null,
        User|string|null $actor = null,
    ): void {
        try {
            $request = $this->requests->getCurrentRequest();
            $ip = $request?->getClientIp();
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            if ($type === ActivityType::LOGIN_FAILED && $this->tooManyFailures($ip, $now)) {
                return;
            }

            [$actorId, $actorName] = $this->resolveActor($actor);

            $this->connection->insert('identity_activity_event', [
                'occurred_at' => $now->format('Y-m-d H:i:s'),
                'actor_user_id' => $actorId,
                'actor_username' => self::clean($actorName, 180),
                'event_type' => $type,
                'is_sensitive' => ActivityType::isSensitive($type) ? 1 : 0,
                'summary' => self::clean($summary, 255) ?? '',
                'detail' => self::clean($detail, 255),
                'subject' => self::clean($subject, 180),
                'ip' => self::clean($ip, 45),
                'user_agent' => self::clean($request?->headers->get('User-Agent'), 255),
            ]);
        } catch (Throwable $exception) {
            // The record observes business operations; it must never be able to fail one.
            $this->logger->warning('Activity event could not be recorded.', [
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /** @return array{0: int|null, 1: string|null} */
    private function resolveActor(User|string|null $actor): array
    {
        if ($actor === null) {
            $actor = $this->security->getUser();
        }
        if ($actor instanceof User) {
            return [$actor->getId(), $actor->getUsername()];
        }

        return [null, is_string($actor) ? $actor : null];
    }

    private function tooManyFailures(?string $ip, DateTimeImmutable $now): bool
    {
        if ($ip === null) {
            return false;
        }
        $recent =
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM identity_activity_event WHERE event_type = :type AND ip = :ip AND occurred_at > :since', [
                'type' => ActivityType::LOGIN_FAILED,
                'ip' => $ip,
                'since' => $now->modify('-60 seconds')->format('Y-m-d H:i:s'),
            ]);

        return $recent >= self::MAX_FAILED_LOGINS_PER_IP_PER_MINUTE;
    }

    /** Strips control characters (log/CSV injection, broken UI) and caps the length. */
    private static function clean(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
