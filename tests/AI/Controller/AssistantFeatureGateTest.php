<?php

declare(strict_types=1);

namespace App\Tests\AI\Controller;

use App\AI\Controller\AssistantActionController;
use App\AI\Controller\AssistantController;
use App\AI\Controller\AssistantStreamController;
use App\AI\Controller\AssistantThreadController;
use App\AI\Controller\BriefingController;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AssistantFeatureGateTest extends TestCase
{
    public function testAssistantControllersRequireAiAssistantFeature(): void
    {
        foreach ([
            AssistantController::class,
            AssistantStreamController::class,
            BriefingController::class,
            AssistantActionController::class,
            AssistantThreadController::class,
        ] as $class) {
            $attributes = new ReflectionClass($class)->getAttributes(RequiresFeature::class);
            self::assertNotEmpty($attributes, $class . ' must have #[RequiresFeature]');
            self::assertSame('ai_assistant', $attributes[0]->newInstance()->featureKey);
        }
    }
}
