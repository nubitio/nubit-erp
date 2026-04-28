<?php

declare(strict_types=1);

namespace App\Tests\Settings\Dto;

use App\Settings\Dto\MenuDto;
use App\Settings\Dto\SubMenuDto;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(MenuDto::class)]
final class MenuDtoTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $dto = new MenuDto(
            text: 'Dashboard',
            icon: 'home',
            path: '/dashboard',
            items: [],
        );

        self::assertSame('Dashboard', $dto->text);
        self::assertSame('home', $dto->icon);
        self::assertSame('/dashboard', $dto->path);
        self::assertSame([], $dto->items);
    }

    public function testItemsDefaultsToEmptyArray(): void
    {
        $dto = new MenuDto(
            text: 'Reports',
            icon: 'chart',
            path: '/reports',
        );

        self::assertSame([], $dto->items);
    }

    public function testItemsCanBeSetToArrayOfSubMenuDtos(): void
    {
        $sub1 = new SubMenuDto(text: 'Sales', path: '/reports/sales');
        $sub2 = new SubMenuDto(text: 'Purchases', path: '/reports/purchases');

        $dto = new MenuDto(
            text: 'Reports',
            icon: 'chart',
            path: '/reports',
            items: [$sub1, $sub2],
        );

        self::assertCount(2, $dto->items);
        self::assertSame($sub1, $dto->items[0]);
        self::assertSame($sub2, $dto->items[1]);
    }
}
