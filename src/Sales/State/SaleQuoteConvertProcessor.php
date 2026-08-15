<?php

declare(strict_types=1);

namespace App\Sales\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use App\Sales\Dto\SaleQuoteConvertDto;
use App\Sales\Entity\SaleQuote;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\SaleQuoteConversionService;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<SaleQuoteConvertDto, SaleRecord>
 */
readonly class SaleQuoteConvertProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private SaleQuoteConversionService $conversionService,
    ) {}

    /**
     * @inheritdoc
     *
     * @param SaleQuoteConvertDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): SaleRecord {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $quote = $this->entityManager->find(SaleQuote::class, $data->id);
        if (!$quote instanceof SaleQuote) {
            throw new NotFoundHttpException('Cotización no encontrada.');
        }

        $userRef = $this->entityManager->getReference(User::class, $user->userId);
        if (!$userRef instanceof User) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        return $this->conversionService->convert($quote, $data, $userRef);
    }
}
