<?php

declare(strict_types=1);

namespace App\Tests\Efact\Web;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * A tenant without a logo makes `logo()` return an empty string. An empty `<link rel="icon" href="">` makes the
 * browser ask the server for /favicon.ico, which does not exist (404 in every console). The shells fall back to the
 * app's own icon.
 */
final class ShellIconFallbackTest extends KernelTestCase
{
    private const string FALLBACK = 'assets/images/nubit-icon.png';

    public function testTheFallbackIconIsShippedWithTheApp(): void
    {
        static::assertFileExists(__DIR__ . '/../../../public/' . self::FALLBACK);
    }

    public function testATenantWithoutALogoGetsTheAppIconNotAnEmptyHref(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        // Outside a tenant request the logo provider answers '' — the same as a tenant that never uploaded one.
        $html = $twig->createTemplate(
            "{% set app_icon = logo() ?: asset('"
            . self::FALLBACK
            . "') %}<link rel=\"icon\" href=\"{{ app_icon }}\"/>",
        )->render();

        static::assertStringContainsString(self::FALLBACK, $html);
        static::assertStringNotContainsString('href=""', $html);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function shells(): iterable
    {
        yield 'the app shell' => ['templates/home/index.html.twig'];
        yield 'the public receipt' => ['templates/receipt/base.html.twig'];
    }

    #[DataProvider('shells')]
    public function testEveryShellDeclaresTheFallback(string $template): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../../' . $template);

        static::assertStringContainsString(self::FALLBACK, $source);
        static::assertStringNotContainsString('rel="icon" href="{{ logo() }}"', $source);
    }
}
