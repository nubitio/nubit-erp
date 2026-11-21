<?php

declare(strict_types=1);

namespace App\Tests\AI\Security;

use App\AI\Entity\AssistantProposedAction;
use App\AI\Security\AssistantToolAccess;
use App\AI\Security\AssistantToolAccessListener;
use App\AI\Service\ConfirmProposedActionService;
use App\Identity\Security\PermissionCheckerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AssistantToolAccessTest extends TestCase
{
    public function testEveryRegisteredToolHasAnAccessRule(): void
    {
        $names = [];
        foreach (glob(__DIR__ . '/../../../src/AI/Tool/*.php') ?: [] as $file) {
            $class = 'App\\AI\\Tool\\' . basename($file, '.php');
            foreach ((new \ReflectionClass($class))->getAttributes(AsTool::class) as $attribute) {
                $names[] = $attribute->newInstance()->name;
            }
        }

        static::assertNotEmpty($names);
        foreach ($names as $name) {
            static::assertTrue(AssistantToolAccess::knows($name), sprintf('assistant tool "%s" has no access rule', $name));
        }
    }

    public function testEveryRuleNamesARealTool(): void
    {
        $names = [];
        foreach (glob(__DIR__ . '/../../../src/AI/Tool/*.php') ?: [] as $file) {
            $class = 'App\\AI\\Tool\\' . basename($file, '.php');
            foreach ((new \ReflectionClass($class))->getAttributes(AsTool::class) as $attribute) {
                $names[] = $attribute->newInstance()->name;
            }
        }

        foreach (array_keys(AssistantToolAccess::PERMISSION_BY_TOOL) as $name) {
            static::assertContains($name, $names, sprintf('"%s" is in the access map but is not a tool', $name));
        }
    }

    public function testEveryKindOfProposalNeedsAPermissionToConfirm(): void
    {
        $kinds = array_filter(
            (new \ReflectionClass(AssistantProposedAction::class))->getConstants(),
            static fn(string $name): bool => str_starts_with($name, 'KIND_'),
            ARRAY_FILTER_USE_KEY,
        );
        $mapped = (new \ReflectionClass(ConfirmProposedActionService::class))->getConstant('PERMISSION_BY_KIND');

        static::assertIsArray($mapped);
        foreach ($kinds as $constant => $kind) {
            static::assertArrayHasKey($kind, $mapped, sprintf('%s can be confirmed without a permission', $constant));
        }
    }

    public function testAToolRunsOnlyForWhoHoldsItsPermission(): void
    {
        $listener = new AssistantToolAccessListener($this->permissions(['sales.view']));

        $listener($this->event('sales_summary'));
        $listener($this->event('search_manual'));

        $this->expectException(AccessDeniedException::class);
        $listener($this->event('accounts_payable_aging'));
    }

    public function testAnUnknownToolIsRefusedRatherThanLetThrough(): void
    {
        $listener = new AssistantToolAccessListener($this->permissions(['*']));

        $this->expectException(AccessDeniedException::class);
        $listener($this->event('a_tool_nobody_ruled_on'));
    }

    /** @param list<string> $granted */
    private function permissions(array $granted): PermissionCheckerInterface
    {
        $checker = $this->createMock(PermissionCheckerInterface::class);
        $checker->method('hasPermission')->willReturnCallback(
            static fn(string $permission): bool => \in_array($permission, $granted, true),
        );

        return $checker;
    }

    private function event(string $tool): ToolCallArgumentsResolved
    {
        return new ToolCallArgumentsResolved(
            new \stdClass(),
            new Tool(new ExecutionReference(\stdClass::class, 'run'), $tool, 'test tool'),
            [],
        );
    }
}
