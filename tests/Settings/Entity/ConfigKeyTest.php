<?php

declare(strict_types=1);

namespace App\Tests\Settings\Entity;

use App\Settings\Entity\ConfigKey;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(ConfigKey::class)]
final class ConfigKeyTest extends TestCase
{
    public function testAllCasesHaveUniqueValues(): void
    {
        $values = array_map(static fn (ConfigKey $key): string => $key->value, ConfigKey::cases());

        self::assertSame(array_unique($values), $values);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideTechnicalKeys')]
    public function testIsTechnicalReturnsTrueForTechnicalKeys(ConfigKey $key): void
    {
        self::assertTrue($key->isTechnical());
    }

    /**
     * @return array<string, array{ConfigKey}>
     */
    public static function provideTechnicalKeys(): array
    {
        return [
            'SALES_V2_MODE'                         => [ConfigKey::SALES_V2_MODE],
            'SALES_V2_DUAL_WRITE_ENABLED'           => [ConfigKey::SALES_V2_DUAL_WRITE_ENABLED],
            'SALES_V2_DUAL_WRITE_STRICT'            => [ConfigKey::SALES_V2_DUAL_WRITE_STRICT],
            'SALES_V2_CUTOVER_DATE'                 => [ConfigKey::SALES_V2_CUTOVER_DATE],
            'SALES_V2_DISABLE_LEGACY_CASH_MOVEMENT' => [ConfigKey::SALES_V2_DISABLE_LEGACY_CASH_MOVEMENT],
            'SALES_V2_DISABLE_LEGACY_STOCK_PROJ'    => [ConfigKey::SALES_V2_DISABLE_LEGACY_STOCK_PROJ],
            'PURCHASES_V2_MODE'                     => [ConfigKey::PURCHASES_V2_MODE],
            'PURCHASES_V2_DUAL_WRITE_ENABLED'       => [ConfigKey::PURCHASES_V2_DUAL_WRITE_ENABLED],
            'PURCHASES_V2_DUAL_WRITE_STRICT'        => [ConfigKey::PURCHASES_V2_DUAL_WRITE_STRICT],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideNonTechnicalKeys')]
    public function testIsTechnicalReturnsFalseForNonTechnicalKeys(ConfigKey $key): void
    {
        self::assertFalse($key->isTechnical());
    }

    /**
     * @return array<string, array{ConfigKey}>
     */
    public static function provideNonTechnicalKeys(): array
    {
        return [
            'IGV'              => [ConfigKey::IGV],
            'AUTO_INVOICE'     => [ConfigKey::AUTO_INVOICE],
            'DEFAULT_CURRENCY' => [ConfigKey::DEFAULT_CURRENCY],
            'PRINT_METHOD'     => [ConfigKey::PRINT_METHOD],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideAllKeys')]
    public function testIsFeatureFlagAlwaysReturnsFalse(ConfigKey $key): void
    {
        self::assertFalse($key->isFeatureFlag());
    }

    /**
     * @return array<string, array{ConfigKey}>
     */
    public static function provideAllKeys(): array
    {
        $result = [];
        foreach (ConfigKey::cases() as $case) {
            $result[$case->name] = [$case];
        }

        return $result;
    }

    public function testFromReturnsCorrectCase(): void
    {
        self::assertSame(ConfigKey::IGV, ConfigKey::from('app_option_igv'));
    }

    public function testTryFromReturnsNullForNonexistentValue(): void
    {
        $value = 'nonexistent';
        // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertNull(ConfigKey::tryFrom($value));
    }
}
