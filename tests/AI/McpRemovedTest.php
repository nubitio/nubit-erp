<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\ControlPlane\Billing\Feature\FeatureCatalog;
use PHPUnit\Framework\TestCase;

final class McpRemovedTest extends TestCase
{
    public function testHydraMcpClassesDoNotExist(): void
    {
        $projectDir = dirname(__DIR__, 2);
        self::assertFileDoesNotExist($projectDir . '/src/AI/ApiResource/InvokeHydraOperation.php');
        self::assertFileDoesNotExist($projectDir . '/src/AI/ApiResource/ReadHydraResource.php');
        self::assertFileDoesNotExist($projectDir . '/src/AI/ApiResource/ApiDocsResource.php');
        self::assertFileDoesNotExist($projectDir . '/src/AI/State/InvokeHydraOperationProcessor.php');
        self::assertFileDoesNotExist($projectDir . '/src/AI/State/ReadHydraResourceProcessor.php');
        self::assertFileDoesNotExist($projectDir . '/src/AI/State/ApiDocsProvider.php');
    }

    public function testMcpRouteAndPackageConfigAreGone(): void
    {
        $projectDir = dirname(__DIR__, 2);
        self::assertFileDoesNotExist($projectDir . '/config/routes/mcp.yaml');
        self::assertFileDoesNotExist($projectDir . '/config/packages/mcp.yaml');
    }

    public function testAiAssistantIsInTheFeatureCatalogueAndMcpIsNot(): void
    {
        self::assertContains('ai_assistant', FeatureCatalog::keys());
        self::assertContains('ai_assistant', FeatureCatalog::configKeys());
        self::assertNotContains('ai_mcp', FeatureCatalog::keys());
    }
}
