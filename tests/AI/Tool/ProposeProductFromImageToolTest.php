<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Service\ProposedActionFactory;
use App\AI\Tool\ProposeProductFromImageTool;
use App\Catalog\Entity\Product;
use App\Catalog\Repository\ProductRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ProposeProductFromImageToolTest extends TestCase
{
    public function testRejectsEmptyNameWithoutPersisting(): void
    {
        $repo = $this->createMock(ProductRepository::class);
        $repo->expects(self::never())->method('findOneBy');

        $tool = new ProposeProductFromImageTool(
            new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor(),
            $repo,
        );

        $result = $tool->proposeProductFromImage('   ');
        self::assertArrayHasKey('error', $result);
        self::assertStringContainsString('nombre', $result['error']);
    }

    public function testRejectsDuplicateProductName(): void
    {
        $repo = $this->createMock(ProductRepository::class);
        $repo->expects(self::once())->method('findOneBy')->with(['name' => 'Pan'])->willReturn($this->createStub(Product::class));

        $tool = new ProposeProductFromImageTool(
            new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor(),
            $repo,
        );

        $result = $tool->proposeProductFromImage('Pan');
        self::assertSame(['error' => 'Ya existe un producto llamado "Pan".'], $result);
    }
}
