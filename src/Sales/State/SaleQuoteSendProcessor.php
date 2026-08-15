<?php

declare(strict_types=1);

namespace App\Sales\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Security\Security;
use App\Sales\Dto\SaleQuoteSendDto;
use App\Sales\Entity\SaleQuote;
use App\Sales\Service\SaleQuoteService;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<SaleQuoteSendDto, SaleQuote>
 */
readonly class SaleQuoteSendProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private SaleQuoteService $saleQuoteService,
    ) {}

    /**
     * @inheritdoc
     *
     * @param SaleQuoteSendDto $data
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SaleQuote
    {
        if ($this->security->connectedUser() === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $quote = $this->entityManager->find(SaleQuote::class, $data->id);
        if (!$quote instanceof SaleQuote) {
            throw new NotFoundHttpException('Cotización no encontrada.');
        }

        $this->saleQuoteService->transitionTo($quote, SaleQuote::STATUS_SENT);
        $this->entityManager->flush();

        return $quote;
    }
}
