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

    /** @return array<string, array{ConfigKey}> */
    public static function provideTechnicalKeys(): array
    {
        return [
            'RECEIPT_REQUIRED'          => [ConfigKey::RECEIPT_REQUIRED],
            'SUNAT_SEND_ENABLED'        => [ConfigKey::SUNAT_SEND_ENABLED],
            'SUNAT_REJECTION_STREAK'    => [ConfigKey::SUNAT_REJECTION_STREAK],
            'SUNAT_REJECTION_THRESHOLD' => [ConfigKey::SUNAT_REJECTION_THRESHOLD],
            'CPE_CLIENT_ID'             => [ConfigKey::CPE_CLIENT_ID],
            'CPE_CLIENT_SECRET'         => [ConfigKey::CPE_CLIENT_SECRET],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideNonTechnicalKeys')]
    public function testIsTechnicalReturnsFalseForNonTechnicalKeys(ConfigKey $key): void
    {
        self::assertFalse($key->isTechnical());
    }

    /** @return array<string, array{ConfigKey}> */
    public static function provideNonTechnicalKeys(): array
    {
        return [
            'IGV'              => [ConfigKey::IGV],
            'AUTO_INVOICE'     => [ConfigKey::AUTO_INVOICE],
            'DEFAULT_CURRENCY' => [ConfigKey::DEFAULT_CURRENCY],
            'PRINT_METHOD'     => [ConfigKey::PRINT_METHOD],
        ];
    }

    public function testFromReturnsCorrectCase(): void
    {
        self::assertSame(ConfigKey::IGV, ConfigKey::from('app_option_igv'));
    }

    public function testTryFromReturnsNullForNonexistentValue(): void
    {
        self::assertNull(ConfigKey::tryFrom('nonexistent'));
    }
}
