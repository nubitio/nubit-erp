<?php

declare(strict_types=1);

namespace App\Tests\Identity\Security;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Billing\Entity\TaxDocument;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Entity\CashSession;
use App\Catalog\Entity\Product;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Purchasing\Entity\Purchase;
use App\Sales\Entity\SaleRecord;
use PHPUnit\Framework\TestCase;

final class CriticalApiPermissionsTest extends TestCase
{
    public function testSaleOperationsEnforceGranularPermissions(): void
    {
        $securities = $this->operationSecurities(SaleRecord::class);

        static::assertContains("is_granted('perm:sales.view')", $securities);
        static::assertContains("is_granted('perm:sales.create')", $securities);
        static::assertContains("is_granted('perm:sales.void')", $securities);
        static::assertContains("is_granted('perm:sales.collect')", $securities);
        static::assertNotContains(null, $securities);
    }

    public function testCashSessionsCannotBypassOpenAndCloseProcessors(): void
    {
        $operations = $this->operations(CashSession::class);

        static::assertEmpty(array_filter(
            $operations,
            static fn(object $operation): bool => $operation instanceof Patch || $operation instanceof Delete,
        ));
        foreach ($operations as $operation) {
            static::assertNotNull($operation->getSecurity());
            if ($operation instanceof Post) {
                static::assertSame('/cash-sessions/{id}/close', $operation->getUriTemplate());
                static::assertSame("is_granted('perm:cash.close')", $operation->getSecurity());
            }
        }
    }

    public function testCashMovementsAreReadOrAppendOnlyWithPermissions(): void
    {
        $operations = $this->operations(CashMovement::class);

        static::assertCount(3, $operations);
        static::assertContains("is_granted('perm:cash.view')", $this->operationSecurities(CashMovement::class));
        static::assertContains("is_granted('perm:cash.movement')", $this->operationSecurities(CashMovement::class));
    }

    public function testPurchaseVoidAndCreditNotesRequireSensitivePermissions(): void
    {
        $purchaseSecurities = $this->operationSecurities(Purchase::class);
        static::assertContains("is_granted('perm:purchases.void')", $purchaseSecurities);
        static::assertContains("is_granted('perm:purchases.edit')", $purchaseSecurities);

        $taxDocumentPost = array_values(array_filter(
            $this->operations(TaxDocument::class),
            static fn(object $operation): bool => $operation instanceof Post && $operation->getUriTemplate() === null,
        ));
        static::assertCount(1, $taxDocumentPost);
        static::assertSame("is_granted('perm:sales.create')", $taxDocumentPost[0]->getSecurity());
        static::assertSame(
            "object.getReasonCode() == null or is_granted('perm:sales.void')",
            $taxDocumentPost[0]->getSecurityPostDenormalize(),
        );
    }

    public function testCatalogAndUserAdministrationCannotBypassPermissions(): void
    {
        $productSecurities = $this->operationSecurities(Product::class);
        static::assertContains("is_granted('perm:catalog.edit')", $productSecurities);
        static::assertContains("is_granted('perm:catalog.delete')", $productSecurities);
        static::assertNotContains(null, $productSecurities);

        $userSecurities = $this->operationSecurities(User::class);
        static::assertContains("is_granted('perm:users.edit')", $userSecurities);
        static::assertContains("is_granted('perm:users.delete')", $userSecurities);
        static::assertNotContains(null, $userSecurities);

        $roleSecurities = $this->operationSecurities(Role::class);
        static::assertContains("is_granted('perm:roles.view')", $roleSecurities);
        static::assertContains("is_granted('perm:roles.edit')", $roleSecurities);
        static::assertNotContains(null, $roleSecurities);
    }

    /** @param class-string $resource */
    private function operationSecurities(string $resource): array
    {
        return array_map(
            static fn(object $operation): ?string => $operation->getSecurity(),
            $this->operations($resource),
        );
    }

    /** @param class-string $resource */
    private function operations(string $resource): array
    {
        $reflection = new \ReflectionClass($resource);
        $apiResource = $reflection->getAttributes(ApiResource::class)[0]->newInstance();

        return iterator_to_array($apiResource->getOperations());
    }
}
