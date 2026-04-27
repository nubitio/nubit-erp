<?php

declare(strict_types=1);

namespace App\Tests\Core\Doctrine\Filter;

use App\Efact\Doctrine\Filter\SoftDeleteFilter;
use App\Organization\Entity\Branch;
use App\Entity\Media;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use App\Identity\Entity\User;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SoftDeleteFilterTest extends TestCase
{
    public function testAddsDeletedAtConstraintForPurchase(): void
    {
        self::assertSame(
            'gr.deleted_at IS NULL',
            $this->buildFilter()->addFilterConstraint($this->createMetadataStub(Purchase::class), 'gr'),
        );
    }

    public function testAddsDeletedAtConstraintForUser(): void
    {
        self::assertSame(
            'u.deleted_at IS NULL',
            $this->buildFilter()->addFilterConstraint($this->createMetadataStub(User::class), 'u'),
        );
    }

    public function testAddsDeletedAtConstraintForMedia(): void
    {
        self::assertSame(
            'm.deleted_at IS NULL',
            $this->buildFilter()->addFilterConstraint($this->createMetadataStub(Media::class), 'm'),
        );
    }

    public function testLeavesOtherEntitiesUntouched(): void
    {
        self::assertSame(
            '',
            $this->buildFilter()->addFilterConstraint($this->createMetadataStub(Branch::class), 'b'),
        );
    }

    public function testDoesNotApplyPurchaseDeletedAtConstraintToPurchaseItems(): void
    {
        self::assertSame(
            '',
            $this->buildFilter()->addFilterConstraint($this->createMetadataStub(PurchaseItem::class), 'gri'),
        );
    }

    private function buildFilter(): SoftDeleteFilter
    {
        return (new ReflectionClass(SoftDeleteFilter::class))->newInstanceWithoutConstructor();
    }

    /**
     * @return ClassMetadata<object>
     */
    private function createMetadataStub(string $entityClass): ClassMetadata
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getName')->willReturn($entityClass);

        return $metadata;
    }
}
