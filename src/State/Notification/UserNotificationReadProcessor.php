<?php

declare(strict_types=1);

namespace App\State\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Entity\UserNotification;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * PATCH /api/user-notifications/{id}/read
 *
 * Marks a single notification as read. Users may only mark their own
 * notifications.
 *
 * @implements ProcessorInterface<UserNotification, UserNotification>
 */
final readonly class UserNotificationReadProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param UserNotification $data
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserNotification
    {
        $user = $this->security->connectedUser();

        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $id = (int) ($uriVariables['id'] ?? 0);

        /** @var UserNotification|null $notification */
        $notification = $this->entityManager->find(UserNotification::class, $id);

        if ($notification === null) {
            throw new NotFoundHttpException('Notificación no encontrada.');
        }

        if ($notification->getUserId() !== $user->userId) {
            throw new AccessDeniedHttpException('No autorizado para modificar esta notificación.');
        }

        if (!$notification->isRead()) {
            $notification->markRead();
            $this->entityManager->flush();
        }

        return $notification;
    }
}
