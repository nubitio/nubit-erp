<?php

declare(strict_types=1);

namespace App\Tests\AI\Controller;

use App\AI\Controller\AssistantController;
use App\AI\Dto\AssistantMessageInput;
use App\AI\Dto\AssistantRequest;
use App\AI\Service\SessionContextBuilder;
use Nubit\Platform\Exception\ServiceException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * Regression test for the assistant rate limiter added 2026-08-19 (guards
 * against unbounded OpenAI spend on POST /api/assistant — see
 * config/packages/rate_limiter.yaml's "assistant" limiter).
 *
 * Uses a real RateLimiterFactory (in-memory storage) rather than a mock:
 * the whole point being tested is Symfony's own limiter behaviour wired
 * into the controller, not a fake we'd have to trust anyway.
 */
#[AllowMockObjectsWithoutExpectations]
final class AssistantControllerRateLimitTest extends TestCase
{
    public function testExhaustedLimiterRejectsWithTooManyRequestsBeforeCallingAgent(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->expects(self::never())->method('call');

        $storage = new InMemoryStorage();
        $limiterFactory = new RateLimiterFactory([
            'id' => 'assistant_test',
            'policy' => 'fixed_window',
            'limit' => 1,
            'interval' => '1 minute',
        ], $storage);

        // Pre-consume the single allowed slot for the 'anonymous' key (the
        // controller falls back to it when getUser() returns null) — the
        // same key AssistantController::__invoke() will use.
        $limiterFactory->create('anonymous')->consume();

        $sessionContextBuilder = new ReflectionClass(SessionContextBuilder::class)->newInstanceWithoutConstructor();

        $controller = new AssistantController(
            $agent,
            $this->createMock(LoggerInterface::class),
            $sessionContextBuilder,
            '/app',
            $limiterFactory,
        );
        $controller->setContainer(new ServiceLocator([
            'security.token_storage' => static fn() => new TokenStorage(),
        ]));

        $message = new AssistantMessageInput();
        $message->role = 'user';
        $message->content = 'hola';

        $request = new AssistantRequest();
        $request->messages = [$message];

        $this->expectException(ServiceException::class);
        $this->expectExceptionCode(Response::HTTP_TOO_MANY_REQUESTS);

        $controller->__invoke($request);
    }
}
