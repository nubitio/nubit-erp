<?php

declare(strict_types=1);

namespace App\Tests\Sales\Reminder;

use App\Sales\Reminder\WhatsappNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WhatsappNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{0: ?string, 1: ?string}>
     */
    public static function phones(): iterable
    {
        yield 'bare mobile gets the Peru prefix' => ['954321678', '51954321678'];
        yield 'formatted with spaces and plus' => ['+51 954 321 678', '51954321678'];
        yield 'dashes' => ['51-954-321-678', '51954321678'];
        yield 'other country kept as is' => ['+1 305 555 0100', '13055550100'];
        yield 'landline without prefix is not guessed' => ['054 123456', null];
        yield 'too short' => ['12345', null];
        yield 'too long' => ['1234567890123456', null];
        yield 'no digits' => ['sin telefono', null];
        yield 'empty' => ['', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('phones')]
    public function testNormalize(?string $input, ?string $expected): void
    {
        self::assertSame($expected, WhatsappNumber::normalize($input));
    }
}
