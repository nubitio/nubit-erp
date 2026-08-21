<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Entity\AssistantProposedAction;
use App\AI\Service\ProposedActionFactory;
use App\Catalog\Repository\ProductRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'propose_product_from_image',
    description: 'Propose a product draft extracted from an attached photo. Does NOT save. '
    . 'Look at the image first, then pass name (required), optional code and salePrice. '
    . 'The UI shows Confirmar.',
    method: 'proposeProductFromImage',
)]
final readonly class ProposeProductFromImageTool
{
    public function __construct(
        private ProposedActionFactory $factory,
        private ProductRepository $productRepository,
    ) {}

    /**
     * @return array{error: string}|array{id: string, kind: string, summary: string, payload: array<string, mixed>}
     */
    public function proposeProductFromImage(string $name, ?string $code = null, ?float $salePrice = null): array
    {
        $name = trim($name);
        $code = $code !== null ? trim($code) : null;

        if ($name === '') {
            return ['error' => 'No pude leer un nombre de producto en la imagen. Indica el nombre.'];
        }

        if ($this->productRepository->findOneBy(['name' => $name]) !== null) {
            return ['error' => sprintf('Ya existe un producto llamado "%s".', $name)];
        }

        if ($code !== null && $code !== '' && $this->productRepository->findOneBy(['code' => $code]) !== null) {
            return ['error' => sprintf('Ya existe un producto con el código %s.', $code)];
        }

        $payload = [
            'name' => $name,
            'source' => 'image',
        ];
        if ($code !== null && $code !== '') {
            $payload['code'] = $code;
        }
        if ($salePrice !== null && $salePrice > 0) {
            $payload['salePrice'] = $salePrice;
        }

        $summary = sprintf('Crear producto desde foto: %s', $name);
        $action = $this->factory->propose(AssistantProposedAction::KIND_CREATE_PRODUCT, $summary, $payload);

        return $action->toCard();
    }
}
