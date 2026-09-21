<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Catalog\Entity\Product;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\ProductRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\ProductKind;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/** Provides the non-inventory catalog concept required by the legacy sale line schema. */
final readonly class OpeningReceivableProductResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private UnitOfMeasureRepository $unitOfMeasureRepository,
    ) {}

    public function resolve(): Product
    {
        $existing = $this->productRepository->findOneBy(['code' => 'OPENING-BALANCE']);
        if ($existing instanceof Product) {
            return $existing;
        }

        $category = $this->categoryRepository->findOneBy([]);
        $unit = $this->unitOfMeasureRepository->findOneBy([]);
        if ($category === null || $unit === null) {
            throw new RuntimeException(
                'No se puede importar saldos: crea al menos una categoría y una unidad de medida.',
            );
        }

        $product = new Product()
            ->setCode('OPENING-BALANCE')
            ->setName('Saldo inicial por cobrar')
            ->setType(2)
            ->setProductKind(ProductKind::SERVICE)
            ->setInventoryBehavior(InventoryBehavior::NOT_TRACKED)
            ->setCategory($category)
            ->setUnitOfMeasure($unit)
            ->setSalePrice('0.00')
            ->setTrackInventory(false);
        $this->entityManager->persist($product);

        return $product;
    }
}
