<?php

declare(strict_types=1);

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Repository\UserNotificationRepository;
use App\Identity\Security\Security;
use Override;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * POST /api/user-notifications/read-all
 *
 * Marks all unread notifications for the current user as read.
 * Uses a DQL bulk update to preserve a single efficient query.
 *
 * @implements ProcessorInterface<null, null>
 */
final readonly class UserNotificationReadAllProcessor implements ProcessorInterface
{
    public function __construct(
        private UserNotificationRepository $notifications,
        private Security $security,
    ) {}

    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->connectedUser();

        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $this->notifications->markAllUnreadAsRead($user->userId, new \DateTimeImmutable());

        return null;
    }
}
