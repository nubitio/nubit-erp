<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Context\BranchContextInterface;
use App\AI\Service\ProposedActionFactory;
use App\AI\Tool\ProposeSaleTool;
use App\Catalog\Repository\ProductRepository;
use App\Contacts\Repository\CustomerRepository;
use App\Settings\Runtime\TenantRuntimeConfigProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bundle\SecurityBundle\Security;

final class ProposeSaleToolTest extends TestCase
{
    public function testRejectsMalformedItemsJsonWithoutPersisting(): void
    {
        $tool = new ProposeSaleTool(
            new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor(),
            $this->createStub(ProductRepository::class),
            $this->createStub(CustomerRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(BranchContextInterface::class),
            new ReflectionClass(TenantRuntimeConfigProvider::class)->newInstanceWithoutConstructor(),
            $this->createStub(Security::class),
        );

        $result = $tool->proposeSale('not-json');

        self::assertArrayHasKey('error', $result);
        self::assertStringContainsString('JSON array', $result['error'] ?? '');
    }
}
