<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Billing\Entity\TaxDocumentEvent;
use App\Notification\NotificationType;
use App\Notification\UserNotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;

/**
 * Listens to Doctrine postPersist on TaxDocumentEvent.
 * When a SUNAT transmission results in an error or rejection, creates an
 * in-app notification for all admin / super-admin users in the tenant.
 */
#[AsDoctrineListener(event: Events::postPersist)]
final readonly class SunatNotificationListener
{
    private const array ERROR_STATUSES = ['error', 'rejected', 'ERROR', 'REJECTED'];

    public function __construct(
        private UserNotificationService $notificationService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param LifecycleEventArgs<\Doctrine\Persistence\ObjectManager> $args
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof TaxDocumentEvent) {
            return;
        }

        $status = $entity->getStatus();

        if ($status === null || !in_array($status, self::ERROR_STATUSES, true)) {
            return;
        }

        $doc       = $entity->getTaxDocument();
        $docId     = $doc->getId() ?? '';
        $code      = $entity->getCode() ?? '';
        $message   = $entity->getMessage() ?? 'Sin detalle';
        $actionUrl = '/sales?filter=sunat_error';

        $title = sprintf('Error SUNAT: %s', $code !== '' ? "Código {$code}" : 'documento rechazado');
        $body  = sprintf('Documento %s fue rechazado por SUNAT: %s', $docId !== '' ? "#{$docId}" : 'reciente', $message);

        try {
            $this->notificationService->notifyByRoles(
                ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'],
                NotificationType::SUNAT_ERROR,
                'critical',
                $title,
                $body,
                $actionUrl,
            );
        } catch (Exception $e) {
            // Never let a notification failure break the main persist flow.
            $this->logger->error('SunatNotificationListener: failed to write notification', [
                'exception'       => $e->getMessage(),
                'taxDocumentId'   => $docId,
                'taxDocumentEvent' => $entity->getId(),
            ]);
        }
    }
}
