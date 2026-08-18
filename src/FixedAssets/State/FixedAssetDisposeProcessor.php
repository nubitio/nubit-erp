<?php

declare(strict_types=1);

namespace App\FixedAssets\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\FixedAssets\Dto\FixedAssetDisposeDto;
use App\FixedAssets\Entity\FixedAsset;
use App\FixedAssets\Service\FixedAssetService;
use App\Identity\Security\Security;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<FixedAssetDisposeDto, FixedAsset>
 */
readonly class FixedAssetDisposeProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private FixedAssetService $fixedAssetService,
    ) {}

    /**
     * @inheritdoc
     *
     * @param FixedAssetDisposeDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): FixedAsset {
        if ($this->security->connectedUser() === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $fixedAsset = $this->entityManager->find(FixedAsset::class, $data->id);
        if (!$fixedAsset instanceof FixedAsset) {
            throw new NotFoundHttpException('Activo fijo no encontrado.');
        }

        $this->fixedAssetService->transitionTo($fixedAsset, FixedAsset::DISPOSED);
        $fixedAsset->setDisposedAt(new DateTimeImmutable());
        $fixedAsset->setDisposalNote($data->disposalNote);

        $this->entityManager->flush();

        return $fixedAsset;
    }
}
