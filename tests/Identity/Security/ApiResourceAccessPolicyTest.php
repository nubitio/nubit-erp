<?php

declare(strict_types=1);

namespace App\Tests\Identity\Security;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use App\Identity\Security\ResourceAccessPolicy;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * An API Platform operation without `security` is open to every signed-in user, whatever the screens
 * show. This walks every resource the kernel exposes, as the framework sees it (after
 * {@see \App\Identity\Security\PolicyResourceMetadataCollectionFactory}), so a new resource cannot
 * ship unguarded: it needs `security` on the entity or a rule in {@see ResourceAccessPolicy}.
 */
final class ApiResourceAccessPolicyTest extends KernelTestCase
{
    private const array INERT_CONTROLLERS = ['api_platform.action.not_exposed', 'api_platform.action.placeholder'];

    public function testNoOperationIsLeftWithoutAnAccessRule(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $names = $container->get(ResourceNameCollectionFactoryInterface::class);
        $metadata = $container->get(ResourceMetadataCollectionFactoryInterface::class);
        $policy = $container->get(ResourceAccessPolicy::class);

        $open = [];
        $checked = 0;
        foreach ($names->create() as $resourceClass) {
            if (
                \in_array($resourceClass, $policy->exempt(), true)
                || str_starts_with($resourceClass, 'ApiPlatform\\')
            ) {
                continue;
            }
            foreach ($metadata->create($resourceClass) as $resource) {
                foreach ($resource->getOperations() ?? [] as $operation) {
                    // Routes the framework registers only to build IRIs or render errors; they never
                    // reach a state provider (the router answers them with 404).
                    if (\in_array($operation->getController(), self::INERT_CONTROLLERS, true)) {
                        continue;
                    }
                    ++$checked;
                    if ($operation instanceof HttpOperation && $operation->getSecurity() === null) {
                        $open[] = sprintf(
                            '%s %s %s',
                            $resourceClass,
                            $operation->getMethod(),
                            $operation->getUriTemplate() ?? '(default)',
                        );
                    }
                }
            }
        }

        static::assertGreaterThan(100, $checked, 'the resource walk found suspiciously few operations');
        static::assertSame([], $open, "Operations without a security rule:\n" . implode("\n", $open));
    }

    public function testEveryRuleNamesARealResource(): void
    {
        self::bootKernel();
        $policy = static::getContainer()->get(ResourceAccessPolicy::class);

        static::assertNotEmpty($policy->rules());
        foreach (array_merge(array_keys($policy->rules()), $policy->exempt()) as $class) {
            static::assertTrue(class_exists($class), $class . ' in resource_access.yaml does not exist');
        }
    }

    public function testWritesNeverFallBackToOpen(): void
    {
        self::bootKernel();
        $policy = static::getContainer()->get(ResourceAccessPolicy::class);

        foreach ($policy->rules() as $class => $rules) {
            foreach ($rules as $key => $rule) {
                $isRead = $key === 'read' || str_starts_with($key, 'GET ');
                // Each user's own notifications are scoped to the caller by their provider.
                if (!$isRead && $class !== \App\Identity\Entity\UserNotification::class) {
                    static::assertNotSame('open', $rule, sprintf('%s "%s" is a write left open', $class, $key));
                }
            }
        }
    }

    public function testRulesResolveByCustomOperationThenMethodThenKind(): void
    {
        self::bootKernel();
        $policy = static::getContainer()->get(ResourceAccessPolicy::class);

        static::assertSame(
            "is_granted('perm:payroll.approve')",
            $policy->expressionFor(\App\Payroll\Entity\PayrollRun::class, 'POST', '/payroll-runs/{id}/approve'),
        );
        static::assertSame(
            "is_granted('perm:payroll.run')",
            $policy->expressionFor(\App\Payroll\Entity\PayrollRun::class, 'PATCH', null),
        );
        static::assertSame(
            ResourceAccessPolicy::OPEN,
            $policy->expressionFor(\App\Catalog\Entity\Category::class, 'GET', null),
        );
        static::assertSame(
            "is_granted('perm:settings.edit') or is_granted('perm:catalog.edit')",
            $policy->expressionFor(\Nubit\AdminBundle\Media\Entity\Media::class, 'DELETE', '/media/{id}'),
        );
        static::assertNull($policy->expressionFor(\App\Sales\Entity\SaleRecord::class, 'GET', null));
    }
}
