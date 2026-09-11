<?php

declare(strict_types=1);

namespace App\Tests\Efact\Audit;

use App\Efact\Audit\AuditLogger;
use App\Efact\Audit\AuditLogMessage;
use App\Efact\Audit\AuditLogMessageHandler;
use Doctrine\Persistence\ConnectionRegistry;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AllowMockObjectsWithoutExpectations]
final class AuditLoggerTest extends TestCase
{
    private TenantContext&MockObject $context;
    private Security&MockObject $security;
    private TenantRegistryInterface&MockObject $tenantManager;
    private LoggerInterface $logger;
    private MessageBusInterface&MockObject $bus;
    private AuditLogger $auditLogger;

    protected function setUp(): void
    {
        $this->context = $this->createMock(TenantContext::class);
        $this->security = $this->createMock(Security::class);
        $this->tenantManager = $this->createMock(TenantRegistryInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $handler = new AuditLogMessageHandler(
            $this->createStub(ConnectionRegistry::class),
            $this->createStub(CacheInterface::class),
            $this->createStub(LoggerInterface::class),
        );

        $this->auditLogger = new AuditLogger(
            $this->context,
            $this->security,
            $this->tenantManager,
            $this->logger,
            $this->bus,
            $handler,
        );
    }

    public function testLogDispatchesAuditMessageWithGivenAction(): void
    {
        $this->context->method('getTenantId')->willReturn(1);
        $this->context->method('getRequestId')->willReturn('req-1');
        $this->context->method('getChannel')->willReturn('http');
        $this->context->method('getActorIdentifier')->willReturn('admin');

        $dispatched = null;
        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(AuditLogMessage::class))
            ->willReturnCallback(function (AuditLogMessage $msg) use (&$dispatched) {
                $dispatched = $msg;
                return new Envelope($msg);
            });

        $this->auditLogger->log('user.created', ['id' => 42]);

        self::assertNotNull($dispatched);
        self::assertSame('user.created', $dispatched->action);
        self::assertSame(1, $dispatched->tenantId);
        self::assertSame('admin', $dispatched->actor);
        self::assertSame('req-1', $dispatched->requestId);
        self::assertStringContainsString('"id":42', $dispatched->metadata);
    }

    public function testLogResolvesActorFromExplicitParam(): void
    {
        $this->context->method('getTenantId')->willReturn(1);
        $this->context->method('getRequestId')->willReturn(null);
        $this->context->method('getChannel')->willReturn(null);
        $this->context->method('getActorIdentifier')->willReturn(null);

        $dispatched = null;
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(function ($msg) use (&$dispatched) {
                $dispatched = $msg;
                return new Envelope($msg);
            });

        $this->auditLogger->log('sale.void', actor: 'cashier@store.com');

        self::assertSame('cashier@store.com', $dispatched->actor);
    }

    public function testLogResolvesActorFromSecurityUser(): void
    {
        $this->context->method('getTenantId')->willReturn(5);
        $this->context->method('getRequestId')->willReturn(null);
        $this->context->method('getChannel')->willReturn(null);
        $this->context->method('getActorIdentifier')->willReturn(null);

        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user@example.com');
        $this->security->method('getUser')->willReturn($user);

        $dispatched = null;
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(function ($msg) use (&$dispatched) {
                $dispatched = $msg;
                return new Envelope($msg);
            });

        $this->auditLogger->log('product.updated');

        self::assertSame('user@example.com', $dispatched->actor);
    }

    public function testLogFallsBackToSystemActorWhenNoUserContext(): void
    {
        $this->context->method('getTenantId')->willReturn(null);
        $this->context->method('getTenantName')->willReturn(null);
        $this->context->method('getRequestId')->willReturn(null);
        $this->context->method('getChannel')->willReturn(null);
        $this->context->method('getActorIdentifier')->willReturn(null);
        $this->security->method('getUser')->willReturn(null);

        $dispatched = null;
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(function ($msg) use (&$dispatched) {
                $dispatched = $msg;
                return new Envelope($msg);
            });

        $this->auditLogger->log('cron.run');

        self::assertSame('system', $dispatched->actor);
    }

    public function testLogUsesTenantIdFromContext(): void
    {
        $this->context->method('getTenantId')->willReturn(99);
        $this->context->method('getRequestId')->willReturn(null);
        $this->context->method('getChannel')->willReturn(null);
        $this->context->method('getActorIdentifier')->willReturn('cli');

        $dispatched = null;
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(function ($msg) use (&$dispatched) {
                $dispatched = $msg;
                return new Envelope($msg);
            });

        $this->auditLogger->log('db.migrated', tenantId: 99);

        self::assertSame(99, $dispatched->tenantId);
    }
}
