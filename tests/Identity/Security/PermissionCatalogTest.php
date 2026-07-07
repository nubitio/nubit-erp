<?php

declare(strict_types=1);

namespace App\Tests\Identity\Security;

use App\Identity\Security\PermissionCatalog;
use PHPUnit\Framework\TestCase;

final class PermissionCatalogTest extends TestCase
{
    public function testKeysAreUnique(): void
    {
        $keys = PermissionCatalog::keys();

        static::assertSame($keys, array_values(array_unique($keys)));
        static::assertNotEmpty($keys);
    }

    public function testEveryPermissionKeyIsPrefixedByItsModuleKey(): void
    {
        foreach (PermissionCatalog::modules() as $moduleKey => $module) {
            static::assertNotSame('', $module['label']);

            foreach (array_keys($module['permissions']) as $permissionKey) {
                static::assertStringStartsWith($moduleKey . '.', $permissionKey);
            }
        }
    }

    public function testIsValid(): void
    {
        static::assertTrue(PermissionCatalog::isValid('sales.create'));
        static::assertTrue(PermissionCatalog::isValid('roles.assign'));
        static::assertFalse(PermissionCatalog::isValid('sales.nonexistent'));
        static::assertFalse(PermissionCatalog::isValid(''));
        static::assertFalse(PermissionCatalog::isValid('*'));
    }
}
