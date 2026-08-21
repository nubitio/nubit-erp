<?php

declare(strict_types=1);

namespace App\Tests\AI\Service;

use App\AI\Dto\AssistantRequest;
use App\AI\Dto\ScreenContextInput;
use App\AI\Service\AssistantPromptAssembler;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class AssistantPromptAssemblerTest extends TestCase
{
    public function testScreenBlockIncludesEntityAndExtra(): void
    {
        $assembler = new ReflectionClass(AssistantPromptAssembler::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AssistantPromptAssembler::class, 'screenBlock');

        $request = new AssistantRequest();
        $screen = new ScreenContextInput();
        $screen->screen = 'pos';
        $screen->entityType = 'sale';
        $screen->entityId = 'abc';
        $screen->extra = ['cartTotal' => 12.5];
        $request->screenContext = $screen;

        $block = $method->invoke($assembler, $request);

        self::assertStringContainsString('Screen: pos', $block);
        self::assertStringContainsString('Tipo de entidad: sale', $block);
        self::assertStringContainsString('cartTotal', $block);
    }

    public function testScreenBlockEmptyWithoutContext(): void
    {
        $assembler = new ReflectionClass(AssistantPromptAssembler::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AssistantPromptAssembler::class, 'screenBlock');

        self::assertSame('', $method->invoke($assembler, new AssistantRequest()));
    }
}
