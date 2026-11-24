<?php

declare(strict_types=1);

namespace App\Tests\Integration\Sales;

use App\Integration\Sales\CreditSaleInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CreditSaleInputTest extends TestCase
{
    private const string ID = '019ab06d-a149-71eb-b66b-3c39d0ffd94d';

    public function testCanonicalFingerprintIgnoresObjectKeyOrderAndDecimalFormatting(): void
    {
        $body = $this->request();
        $first = CreditSaleInput::fromArray($body);
        $body['lines'][0]['quantity'] = '2.000';
        $body['lines'][0]['unit_price'] = '10.0000';
        $second = CreditSaleInput::fromArray(array_reverse($body));

        static::assertSame('20.00', $first->expectedTotal);
        static::assertSame($first->fingerprint(), $second->fingerprint());
    }

    public function testPaymentsCannotBeSmuggledIntoACreditSale(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        CreditSaleInput::fromArray($this->request() + ['payments' => []]);
    }

    /** @return array{branch_id: string, warehouse_id: string, customer_id: int, total_minor: int, lines: list<array{product_id: string, quantity: string, unit_price: string}>} */
    private function request(): array
    {
        return [
            'branch_id' => self::ID,
            'warehouse_id' => self::ID,
            'customer_id' => 17,
            'total_minor' => 2000,
            'lines' => [['product_id' => self::ID, 'quantity' => '2', 'unit_price' => '10']],
        ];
    }
}
