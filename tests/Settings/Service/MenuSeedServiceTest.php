<?php

declare(strict_types=1);

namespace App\Tests\Settings\Service;

use App\Settings\Service\MenuSeedService;
use PHPUnit\Framework\TestCase;

final class MenuSeedServiceTest extends TestCase
{
    public function testEverySeededRouteIsAbsolute(): void
    {
        $menus = (new \ReflectionClass(MenuSeedService::class))->getConstant('MENUS');

        foreach ($menus as $menu) {
            $route = $menu['route'];
            if ($route !== null && $route !== '') {
                static::assertStringStartsWith('/', $route, sprintf('Menu "%s" route must be absolute.', $menu['key_name']));
            }
        }
    }
}
