<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Entity\AssistantProposedAction;
use App\AI\Service\ProposedActionFactory;
use App\Catalog\Repository\ProductRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'propose_product',
    description: 'Propose creating a product. Does NOT save. The UI shows a confirmation card. '
    . 'Pass name (required) and optional code and salePrice.',
    method: 'proposeProduct',
)]
final readonly class ProposeProductTool
{
    public function __construct(
        private ProposedActionFactory $factory,
        private ProductRepository $productRepository,
    ) {}

    /**
     * @return array{error: string}|array{id: string, kind: string, summary: string, payload: array<string, mixed>, expiresAt: string}
     */
    public function proposeProduct(string $name, ?string $code = null, ?float $salePrice = null): array
    {
        $name = trim($name);
        $code = $code !== null ? trim($code) : null;

        if ($name === '') {
            return ['error' => 'El nombre del producto es obligatorio.'];
        }

        $existingByName = $this->productRepository->findOneBy(['name' => $name]);
        if ($existingByName !== null) {
            return ['error' => sprintf('Ya existe un producto llamado "%s".', $name)];
        }

        if ($code !== null && $code !== '') {
            $existingByCode = $this->productRepository->findOneBy(['code' => $code]);
            if ($existingByCode !== null) {
                return ['error' => sprintf('Ya existe un producto con el código %s.', $code)];
            }
        }

        $payload = ['name' => $name];
        if ($code !== null && $code !== '') {
            $payload['code'] = $code;
        }
        if ($salePrice !== null && $salePrice > 0) {
            $payload['salePrice'] = $salePrice;
        }

        $summary =
            $code !== null && $code !== ''
                ? sprintf('Crear producto %s (%s)', $name, $code)
                : sprintf('Crear producto %s', $name);

        $action = $this->factory->propose(AssistantProposedAction::KIND_CREATE_PRODUCT, $summary, $payload);

        return $action->toCard();
    }
}
