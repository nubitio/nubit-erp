<?php

declare(strict_types=1);

namespace App\Tests\Billing\EventListener;

use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentEvent;
use App\Billing\EventListener\SunatNotificationListener;
use App\Identity\Notification\NotificationType;
use App\Identity\Notification\UserNotificationServiceInterface;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SunatNotificationListenerTest extends TestCase
{
    /**
     * @return LifecycleEventArgs<EntityManagerInterface>
     */
    private function makeArgs(object $entity): LifecycleEventArgs
    {
        $args = $this->createStub(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($entity);

        /** @phpstan-ignore varTag.nativeType */ /** @var LifecycleEventArgs<EntityManagerInterface> $args */
        return $args;
    }

    /** @return array<string, array{string}> */
    public static function errorStatuses(): array
    {
        return [
            'lowercase error'   => ['error'],
            'lowercase rejected' => ['rejected'],
            'uppercase ERROR'   => ['ERROR'],
            'uppercase REJECTED' => ['REJECTED'],
        ];
    }

    #[DataProvider('errorStatuses')]
    public function testNotifiesAdminsOnErrorStatus(string $status): void
    {
        $doc = new TaxDocument();
        $event = new TaxDocumentEvent();
        $event->setTaxDocument($doc)->setStatus($status)->setCode('2800');
        $event->setMessage('RUC no activo');

        $notifier = $this->createMock(UserNotificationServiceInterface::class);
        $notifier->expects(self::once())->method('notifyByRoles')
            ->with(
                ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'],
                NotificationType::SUNAT_ERROR,
                'critical',
                self::stringContains('Error SUNAT'),
                self::stringContains('rechazado'),
                '/sales?filter=sunat_error',
            );

        $listener = new SunatNotificationListener($notifier, $this->createStub(LoggerInterface::class));
        $listener->postPersist($this->makeArgs($event));
    }

    public function testSkipsNonTaxDocumentEvent(): void
    {
        $notifier = $this->createMock(UserNotificationServiceInterface::class);
        $notifier->expects(self::never())->method('notifyByRoles');

        $listener = new SunatNotificationListener($notifier, $this->createStub(LoggerInterface::class));
        $listener->postPersist($this->makeArgs(new TaxDocument()));
    }

    public function testSkipsWhenStatusIsNotAnError(): void
    {
        $doc = new TaxDocument();
        $event = new TaxDocumentEvent();
        $event->setTaxDocument($doc)->setStatus('accepted');

        $notifier = $this->createMock(UserNotificationServiceInterface::class);
        $notifier->expects(self::never())->method('notifyByRoles');

        $listener = new SunatNotificationListener($notifier, $this->createStub(LoggerInterface::class));
        $listener->postPersist($this->makeArgs($event));
    }

    public function testSkipsWhenStatusIsNull(): void
    {
        $doc = new TaxDocument();
        $event = new TaxDocumentEvent();
        $event->setTaxDocument($doc);

        $notifier = $this->createMock(UserNotificationServiceInterface::class);
        $notifier->expects(self::never())->method('notifyByRoles');

        $listener = new SunatNotificationListener($notifier, $this->createStub(LoggerInterface::class));
        $listener->postPersist($this->makeArgs($event));
    }

    public function testLogsAndSwallowsNotificationException(): void
    {
        $doc = new TaxDocument();
        $event = new TaxDocumentEvent();
        $event->setTaxDocument($doc)->setStatus('error')->setCode('0001');
        $event->setMessage('DB error');

        $notifier = $this->createStub(UserNotificationServiceInterface::class);
        $notifier->method('notifyByRoles')->willThrowException(
            $this->createStub(DbalException::class)
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')
            ->with('SunatNotificationListener: failed to write notification', self::isArray());

        $listener = new SunatNotificationListener($notifier, $logger);
        // Must not throw
        $listener->postPersist($this->makeArgs($event));
    }
}
