<?php

declare(strict_types=1);

namespace App\Tests\AccountsPayable\Report;

use App\AccountsPayable\Report\PayablesAgingQuery;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Runs the real DQL against the test database (schema comes from the migrations). */
final class PayablesAgingQueryIntegrationTest extends KernelTestCase
{
    public function testQueryRunsAgainstTheMappedSchemaAndReturnsAnEmptyReportForUnknownBranch(): void
    {
        self::bootKernel();
        $query = self::getContainer()->get(PayablesAgingQuery::class);
        self::assertInstanceOf(PayablesAgingQuery::class, $query);

        self::assertSame(['detail' => [], 'suppliers' => [], 'totals' => []], $query->execute('999999', '2026-11-06'));
    }
}
