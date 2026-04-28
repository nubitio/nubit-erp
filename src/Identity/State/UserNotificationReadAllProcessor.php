<?php

declare(strict_types=1);

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * POST /api/user-notifications/read-all
 *
 * Marks all unread notifications for the current user as read.
 * Uses raw DBAL UPDATE for a single efficient query.
 *
 * @implements ProcessorInterface<null, null>
 */
final readonly class UserNotificationReadAllProcessor implements ProcessorInterface
{
    public function __construct(
        private Connection $connection,
        private Security $security,
    ) {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->connectedUser();

        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $this->connection->executeStatement(
            'UPDATE user_notification SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL',
            [$user->userId],
        );

        return null;
    }
}
