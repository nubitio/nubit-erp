<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Service\ProposedActionFactory;
use App\AI\Tool\ProposeCustomerTool;
use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ProposeCustomerToolTest extends TestCase
{
    public function testRejectsInvalidRucWithoutPersisting(): void
    {
        $factory = new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor();
        $repo = $this->createMock(CustomerRepository::class);
        $repo->expects(self::never())->method('findOneBy');

        $tool = new ProposeCustomerTool($factory, $repo);
        $result = $tool->proposeCustomer('12', 'Acme');

        self::assertSame(['error' => 'El RUC/DNI debe tener 8 u 11 dígitos.'], $result);
    }

    public function testRejectsDuplicateCustomer(): void
    {
        $factory = new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor();
        $repo = $this->createMock(CustomerRepository::class);
        $repo->expects(self::once())->method('findOneBy')->with(['ruc' => '20123456789'])->willReturn(new Customer());

        $tool = new ProposeCustomerTool($factory, $repo);
        $result = $tool->proposeCustomer('20123456789', 'Acme SAC');

        self::assertSame(['error' => 'Ya existe un cliente con el documento 20123456789.'], $result);
    }
}
