<?php

declare(strict_types=1);

namespace App\Tests\Settings\Entity;

use App\Settings\Entity\ConfigType;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(ConfigType::class)]
final class ConfigTypeTest extends TestCase
{
    public function testAllFiveCasesExist(): void
    {
        $cases = ConfigType::cases();

        self::assertCount(5, $cases);
    }

    public function testCaseValues(): void
    {
        self::assertSame('string', ConfigType::STRING->value);
        self::assertSame('int', ConfigType::INT->value);
        self::assertSame('float', ConfigType::FLOAT->value);
        self::assertSame('bool', ConfigType::BOOLEAN->value);
        self::assertSame('json', ConfigType::JSON->value);
    }

    public function testAllValuesAreUnique(): void
    {
        $values = array_map(static fn (ConfigType $t): string => $t->value, ConfigType::cases());

        self::assertSame(array_unique($values), $values);
    }

    public function testFromReturnsCorrectCase(): void
    {
        self::assertSame(ConfigType::STRING, ConfigType::from('string'));
    }

    public function testTryFromReturnsNullForNonexistentValue(): void
    {
        $value = 'nonexistent';
        // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertNull(ConfigType::tryFrom($value));
    }
}
