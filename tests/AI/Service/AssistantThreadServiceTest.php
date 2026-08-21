<?php

declare(strict_types=1);

namespace App\Tests\AI\Service;

use App\AI\Dto\AssistantMessageInput;
use App\AI\Service\AssistantThreadService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AssistantThreadServiceTest extends TestCase
{
    public function testLastUserContentReadsTheTrailingUserTurn(): void
    {
        $service = new \ReflectionClass(AssistantThreadService::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AssistantThreadService::class, 'lastUserContent');

        $older = new AssistantMessageInput();
        $older->role = 'user';
        $older->content = 'primero';
        $assistant = new AssistantMessageInput();
        $assistant->role = 'assistant';
        $assistant->content = 'ok';
        $latest = new AssistantMessageInput();
        $latest->role = 'user';
        $latest->content = '  segunda pregunta  ';

        self::assertSame('segunda pregunta', $method->invoke($service, [$older, $assistant, $latest]));
    }
}
