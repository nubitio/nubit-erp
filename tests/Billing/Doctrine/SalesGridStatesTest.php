<?php

declare(strict_types=1);

namespace App\Tests\Billing\Doctrine;

use App\Billing\Doctrine\Filter\SalesGridVirtualFields;
use App\Sales\Entity\SaleRecord;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SalesGridStatesTest extends KernelTestCase
{
    public function test_state_filters_compile_to_sql_without_pagination_joins(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $filter = new SalesGridVirtualFields();
        foreach (['accepted', 'pending', 'not_applicable'] as $state) {
            $qb = $em->createQueryBuilder()->select('o.id')->from(SaleRecord::class, 'o');
            $filter->applyFilter($qb, SaleRecord::class, 'sunatState', '=', $state);
            $sql = $qb->getQuery()->getSQL();
            self::assertStringContainsString('tax_document', $sql);
            self::assertStringContainsString('NOT EXISTS', $sql);
            self::assertStringNotContainsString('JOIN', $sql);
        }
        $qb = $em->createQueryBuilder()->select('o.id')->from(SaleRecord::class, 'o');
        $filter->applyFilter($qb, SaleRecord::class, 'paymentState', '=', 'due');
        self::assertStringContainsString('CASE WHEN', $qb->getQuery()->getSQL());
        self::assertSame('due', $qb->getParameters()->first()->getValue());
        $this->expectException(BadRequestHttpException::class);
        $filter->applyFilter($qb, SaleRecord::class, 'sunatState', '=', "invalid' OR 1=1");
    }
}
