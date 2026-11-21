<?php

declare(strict_types=1);

namespace App\Tests\Identity\Security;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use App\Catalog\Entity\Category;
use App\Identity\Entity\UserNotification;
use App\Identity\Security\ResourceAccessPolicy;
use App\Payroll\Entity\PayrollRun;
use App\Sales\Entity\SaleRecord;
use Nubit\AdminBundle\Media\Entity\Media;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * An API Platform operation without `security` is open to every signed-in user, whatever the screens
 * show. This walks every resource the kernel exposes, as the framework sees it (after
 * {@see \App\Identity\Security\PolicyResourceMetadataCollectionFactory}), so a new resource cannot
 * ship unguarded: it needs `security` on the entity or a rule in `config/packages/resource_access.yaml`.
 */
final class ApiResourceAccessPolicyTest extends KernelTestCase
{
    public function testNoOperationIsLeftWithoutAnAccessRule(): void
    {
        $inventory = $this->inventory();

        static::assertGreaterThan(
            100,
            \count($inventory->operations()),
            'the resource walk found suspiciously few operations',
        );
        static::assertSame([], $inventory->unguarded(), 'operations without a security rule');
    }

    public function testEveryRuleNamesARealResource(): void
    {
        $policy = $this->policy();

        static::assertNotEmpty($policy->rules());
        foreach (array_merge(array_keys($policy->rules()), $policy->exempt()) as $class) {
            static::assertTrue(class_exists($class), $class . ' in resource_access.yaml does not exist');
        }
    }

    public function testWritesNeverFallBackToOpen(): void
    {
        $writesLeftOpen = [];
        foreach ($this->policy()->rules() as $class => $rules) {
            foreach ($rules as $key => $rule) {
                if ($rule === 'open' && !self::isRead($key) && $class !== UserNotification::class) {
                    $writesLeftOpen[] = $class . ' ' . $key;
                }
            }
        }

        static::assertSame(
            [],
            $writesLeftOpen,
            'writes left open (notifications are scoped to the caller by their provider)',
        );
    }

    public function testRulesResolveByCustomOperationThenMethodThenKind(): void
    {
        $policy = $this->policy();

        static::assertSame("is_granted('perm:payroll.approve')", $policy->expressionFor(
            PayrollRun::class,
            'POST',
            '/payroll-runs/{id}/approve',
        ));
        static::assertSame("is_granted('perm:payroll.run')", $policy->expressionFor(PayrollRun::class, 'PATCH', null));
        static::assertSame(ResourceAccessPolicy::OPEN, $policy->expressionFor(Category::class, 'GET', null));
        static::assertSame("is_granted('perm:settings.edit') or is_granted('perm:catalog.edit')", $policy->expressionFor(
            Media::class,
            'DELETE',
            '/media/{id}',
        ));
        static::assertNull($policy->expressionFor(SaleRecord::class, 'GET', null));
    }

    private function inventory(): ApiOperationInventory
    {
        self::bootKernel();
        $container = static::getContainer();
        $names = $container->get(ResourceNameCollectionFactoryInterface::class);
        $metadata = $container->get(ResourceMetadataCollectionFactoryInterface::class);
        static::assertInstanceOf(ResourceNameCollectionFactoryInterface::class, $names);
        static::assertInstanceOf(ResourceMetadataCollectionFactoryInterface::class, $metadata);

        return new ApiOperationInventory($names, $metadata, $this->policy()->exempt());
    }

    private function policy(): ResourceAccessPolicy
    {
        self::bootKernel();
        $policy = static::getContainer()->get(ResourceAccessPolicy::class);
        static::assertInstanceOf(ResourceAccessPolicy::class, $policy);

        return $policy;
    }

    private static function isRead(string $key): bool
    {
        return $key === 'read' || str_starts_with($key, 'GET ');
    }
}
