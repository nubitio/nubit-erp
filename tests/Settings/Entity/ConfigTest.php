<?php

declare(strict_types=1);

namespace App\Tests\Settings\Entity;

use App\Settings\Entity\Config;
use App\Settings\Entity\ConfigType;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    public function testGetIdReturnsNullBeforePersistence(): void
    {
        $config = new Config();

        self::assertNull($config->getId());
    }

    public function testMapsToTenantSettingsTable(): void
    {
        $attributes = (new \ReflectionClass(Config::class))->getAttributes(\Doctrine\ORM\Mapping\Table::class);

        self::assertCount(1, $attributes);
        self::assertSame('tenant_settings', $attributes[0]->newInstance()->name);
    }

    public function testSetNameGetNameRoundTrip(): void
    {
        $config = new Config();

        $result = $config->setName('my_config');

        self::assertSame('my_config', $config->getName());
        self::assertSame($config, $result);
    }

    public function testSetValueGetValueRoundTrip(): void
    {
        $config = new Config();

        $result = $config->setValue('my_value');

        self::assertSame('my_value', $config->getValue());
        self::assertSame($config, $result);
    }

    public function testSetTypeGetTypeRoundTripWithString(): void
    {
        $config = new Config();

        $result = $config->setType(ConfigType::STRING);

        self::assertSame(ConfigType::STRING, $config->getType());
        self::assertSame($config, $result);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideAllConfigTypes')]
    public function testAllConfigTypeCasesCanBeStored(ConfigType $type): void
    {
        $config = new Config();
        $config->setType($type);

        self::assertSame($type, $config->getType());
    }

    /**
     * @return array<string, array{ConfigType}>
     */
    public static function provideAllConfigTypes(): array
    {
        return [
            'STRING'  => [ConfigType::STRING],
            'INT'     => [ConfigType::INT],
            'FLOAT'   => [ConfigType::FLOAT],
            'BOOLEAN' => [ConfigType::BOOLEAN],
            'JSON'    => [ConfigType::JSON],
        ];
    }
}
