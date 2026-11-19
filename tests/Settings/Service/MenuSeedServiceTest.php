<?php

declare(strict_types=1);

namespace App\Tests\Settings\Service;

use App\Settings\Service\MenuSeedService;
use PHPUnit\Framework\TestCase;

final class MenuSeedServiceTest extends TestCase
{
    public function testEverySeededRouteIsAbsolute(): void
    {
        $menus = new \ReflectionClass(MenuSeedService::class)->getConstant('MENUS');
        if (!is_array($menus)) {
            static::fail('Menu seed is not an array.');
        }

        foreach ($menus as $menu) {
            if (!is_array($menu) || !array_key_exists('route', $menu) || !is_string($menu['key_name'] ?? null)) {
                static::fail('Menu seed row is missing its expected route or key name.');
            }

            $route = $menu['route'];
            if ($route !== null && $route !== '') {
                if (!is_string($route)) {
                    static::fail(sprintf('Menu "%s" route must be a string.', $menu['key_name']));
                }

                static::assertStringStartsWith(
                    '/',
                    $route,
                    sprintf('Menu "%s" route must be absolute.', $menu['key_name']),
                );
            }
        }
    }
}
