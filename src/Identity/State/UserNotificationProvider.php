<?php

declare(strict_types=1);

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Identity\Security\Security;
use App\Identity\Entity\UserNotification;
use Doctrine\ORM\EntityManagerInterface;
use Override;

/**
 * Provides the collection of UserNotification for the currently authenticated
 * user: unread + notifications from the last 7 days, newest first, max 50.
 *
 * @implements ProviderInterface<UserNotification>
 */
final readonly class UserNotificationProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @return list<UserNotification>
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->connectedUser();

        if ($user === null) {
            return [];
        }

        /** @var list<UserNotification> $result */
        $result = $this->entityManager
            ->createQuery(
                <<<'DQL'
                    SELECT n FROM App\Identity\Entity\UserNotification n
                    WHERE n.userId = :userId
                      AND (n.readAt IS NULL OR n.createdAt >= :since)
                    ORDER BY n.createdAt DESC
                DQL,
            )
            ->setParameter('userId', $user->userId)
            ->setParameter('since', new \DateTimeImmutable('-7 days'))
            ->setMaxResults(50)
            ->getResult();

        return $result;
    }
}
