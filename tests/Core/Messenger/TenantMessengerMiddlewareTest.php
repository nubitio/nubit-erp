<?php

declare(strict_types=1);

namespace App\Tests\Core\Messenger;

use App\Core\Context\TenantContext;
use App\Core\Messenger\TenantContextMiddleware;
use App\Core\Messenger\TenantStamp;
use App\Core\Messenger\TenantStampMiddleware;
use App\Core\Service\TenantConnectionSwitcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class TenantMessengerMiddlewareTest extends TestCase
{
    public function testStampMiddlewareAddsTenantStamp(): void
    {
        $ctx = new TenantContext();
        $ctx->setTenant(1, 'acme', 'acme.example.com', 'req-1');

        $middleware = new TenantStampMiddleware($ctx);
        $envelope = new Envelope(new \stdClass());
        $stack = $this->createPassthroughStack();

        $result = $middleware->handle($envelope, $stack);

        $stamp = $result->last(TenantStamp::class);
        $this->assertNotNull($stamp);
        $this->assertSame(1, $stamp->tenantId);
        $this->assertSame('acme', $stamp->tenantName);
        $this->assertSame('acme.example.com', $stamp->tenantDomain);
        $this->assertSame('req-1', $stamp->requestId);
    }

    public function testStampMiddlewareDoesNotOverwriteExistingStamp(): void
    {
        $ctx = new TenantContext();
        $ctx->setTenant(1, 'acme', 'acme.example.com', 'req-1');

        $existingStamp = new TenantStamp(99, 'original', 'original.com', 'orig-req');
        $envelope = new Envelope(new \stdClass(), [$existingStamp]);

        $middleware = new TenantStampMiddleware($ctx);
        $stack = $this->createPassthroughStack();

        $result = $middleware->handle($envelope, $stack);

        $stamp = $result->last(TenantStamp::class);
        $this->assertSame(99, $stamp->tenantId);
        $this->assertSame('original', $stamp->tenantName);
    }

    public function testContextMiddlewareRestoresTenantContext(): void
    {
        $ctx = new TenantContext();
        $switcher = $this->createMock(TenantConnectionSwitcher::class);

        $switcher->expects($this->once())
            ->method('switchConnection')
            ->with('acme');

        $stamp = new TenantStamp(1, 'acme', 'acme.example.com', 'req-1');
        $envelope = new Envelope(new \stdClass(), [$stamp]);

        $middleware = new TenantContextMiddleware($ctx, $switcher);

        $capturedTenantName = null;
        $stack = $this->createCapturingStack(function () use ($ctx, &$capturedTenantName): void {
            $capturedTenantName = $ctx->getTenantName();
        });

        $middleware->handle($envelope, $stack);

        $this->assertSame('acme', $capturedTenantName);
    }

    public function testContextMiddlewareClearsContextAfterProcessing(): void
    {
        $ctx = new TenantContext();
        $switcher = $this->createMock(TenantConnectionSwitcher::class);

        $stamp = new TenantStamp(1, 'acme', 'acme.example.com', 'req-1');
        $envelope = new Envelope(new \stdClass(), [$stamp]);

        $middleware = new TenantContextMiddleware($ctx, $switcher);
        $stack = $this->createPassthroughStack();

        $middleware->handle($envelope, $stack);

        $this->assertNull($ctx->getTenantId(), 'Context must be cleared after processing');
        $this->assertNull($ctx->getTenantName(), 'Context must be cleared after processing');
    }

    public function testContextMiddlewareClearsContextEvenOnException(): void
    {
        $ctx = new TenantContext();
        $switcher = $this->createMock(TenantConnectionSwitcher::class);

        $stamp = new TenantStamp(1, 'acme', 'acme.example.com', 'req-1');
        $envelope = new Envelope(new \stdClass(), [$stamp]);

        $middleware = new TenantContextMiddleware($ctx, $switcher);
        $stack = $this->createThrowingStack(new \RuntimeException('Handler failed'));

        try {
            $middleware->handle($envelope, $stack);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertNull($ctx->getTenantId(), 'Context must be cleared even on exception');
        $this->assertNull($ctx->getTenantName(), 'Context must be cleared even on exception');
    }

    public function testContextMiddlewareSkipsWhenNoStamp(): void
    {
        $ctx = new TenantContext();
        $switcher = $this->createMock(TenantConnectionSwitcher::class);

        $switcher->expects($this->never())->method('switchConnection');

        $envelope = new Envelope(new \stdClass());
        $middleware = new TenantContextMiddleware($ctx, $switcher);
        $stack = $this->createPassthroughStack();

        $middleware->handle($envelope, $stack);

        $this->assertNull($ctx->getTenantName());
    }

    public function testContextMiddlewareSkipsWhenTenantNameIsEmpty(): void
    {
        $ctx = new TenantContext();
        $switcher = $this->createMock(TenantConnectionSwitcher::class);

        $switcher->expects($this->never())->method('switchConnection');

        $stamp = new TenantStamp(null, '', null, null);
        $envelope = new Envelope(new \stdClass(), [$stamp]);

        $middleware = new TenantContextMiddleware($ctx, $switcher);
        $stack = $this->createPassthroughStack();

        $middleware->handle($envelope, $stack);
    }

    private function createPassthroughStack(): StackInterface
    {
        $nextMiddleware = new class () implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $envelope;
            }
        };

        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        return $stack;
    }

    private function createCapturingStack(\Closure $callback): StackInterface
    {
        $nextMiddleware = new class ($callback) implements MiddlewareInterface {
            public function __construct(private readonly \Closure $callback)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                ($this->callback)();

                return $envelope;
            }
        };

        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        return $stack;
    }

    private function createThrowingStack(\Throwable $exception): StackInterface
    {
        $nextMiddleware = new class ($exception) implements MiddlewareInterface {
            public function __construct(private readonly \Throwable $exception)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                throw $this->exception;
            }
        };

        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($nextMiddleware);

        return $stack;
    }
}
