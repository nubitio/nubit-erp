<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Service\ProposedActionFactory;
use App\AI\Tool\ProposeOnboardingFillTool;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ProposeOnboardingFillToolTest extends TestCase
{
    public function testStripsCertificateAndPasswordFields(): void
    {
        $tool = new ProposeOnboardingFillTool(
            new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor(),
        );

        $result = $tool->proposeOnboardingFill('{"solPass":"secret","certificate":"x.pfx"}');

        self::assertArrayHasKey('error', $result);
        self::assertStringContainsString('campos permitidos', $result['error']);
    }

    public function testRejectsInvalidJson(): void
    {
        $tool = new ProposeOnboardingFillTool(
            new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor(),
        );

        $result = $tool->proposeOnboardingFill('nope');
        self::assertArrayHasKey('error', $result);
    }
}
