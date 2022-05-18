<?php

namespace App\Domain\Product;

use App\Domain\Service\SerializerService;
use Symfony\Component\Uid\Uuid;

class CreateProduct
{
    private IProductRepository $productRepository;
    private SerializerService $serializerService;

    public function __construct(IProductRepository $productRepository, SerializerService $serializerService)
    {
        $this->productRepository = $productRepository;
        $this->serializerService = $serializerService;
    }

    public function execute(array $data): void
    {
        $data['producto_id'] = Uuid::v4();
        $product = $this->serializerService->deserialize(json_encode($data), Product::class, 'json');
        $json = $this->serializerService->serialize($product, 'json');
        dd($json);
        $this->productRepository->insert($product);
    }
}