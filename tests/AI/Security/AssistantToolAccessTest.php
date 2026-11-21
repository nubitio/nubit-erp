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
        $unruled = array_filter(
            self::registeredTools(),
            static fn(string $name): bool => !AssistantToolAccess::knows($name),
        );

        static::assertNotEmpty(self::registeredTools());
        static::assertSame([], array_values($unruled), 'assistant tools without an access rule');
    }

    public function testEveryRuleNamesARealTool(): void
    {
        $unknown = array_diff(array_keys(AssistantToolAccess::PERMISSION_BY_TOOL), self::registeredTools());

        static::assertSame([], array_values($unknown), 'access rules for tools that do not exist');
    }

    public function testEveryKindOfProposalNeedsAPermissionToConfirm(): void
    {
        $constants = new \ReflectionClass(AssistantProposedAction::class)->getConstants();
        $kinds = array_values(array_filter(
            array_map('strval', array_values(array_filter($constants, 'is_string'))),
            static fn(string $value): bool => \in_array($value, self::proposalKinds(), true),
        ));
        $mapped = new \ReflectionClass(ConfirmProposedActionService::class)->getConstant('PERMISSION_BY_KIND');

        static::assertIsArray($mapped);
        static::assertNotEmpty($kinds);
        static::assertSame(
            [],
            array_values(array_diff($kinds, array_keys($mapped))),
            'kinds confirmable without a permission',
        );
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

    /** @return list<string> */
    private static function registeredTools(): array
    {
        $names = [];
        foreach (glob(__DIR__ . '/../../../src/AI/Tool/*.php') ?: [] as $file) {
            /** @var class-string $class */
            $class = 'App\\AI\\Tool\\' . basename($file, '.php');
            foreach (new \ReflectionClass($class)->getAttributes(AsTool::class) as $attribute) {
                $names[] = $attribute->newInstance()->name;
            }
        }

        return $names;
    }

    /** @return list<string> the values of the KIND_* constants */
    private static function proposalKinds(): array
    {
        $kinds = [];
        foreach (new \ReflectionClass(AssistantProposedAction::class)->getReflectionConstants() as $constant) {
            $value = $constant->getValue();
            if (str_starts_with($constant->getName(), 'KIND_') && \is_string($value)) {
                $kinds[] = $value;
            }
        }

        return $kinds;
    }

    /** @param list<string> $granted */
    private function permissions(array $granted): PermissionCheckerInterface
    {
        $checker = $this->createStub(PermissionCheckerInterface::class);
        $checker
            ->method('hasPermission')
            ->willReturnCallback(static fn(string $permission): bool => \in_array($permission, $granted, true));

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
