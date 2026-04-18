<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Domain;

use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use Brick\Math\Exception\MathException;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

readonly class NoteSendUseCase
{
    public function __construct(
        private NoteCreateUseCase $noteCreateUseCase,
        private NoteRepositoryInterface $noteRepository,
        private LoggerInterface $billingLogger,
    ) {
    }

    /**
     * @throws MathException
     * @throws Exception
     */
    public function __invoke(string $taxDocumentId): DocumentResponse
    {
        $this->billingLogger->info('NoteSendUseCase invoked', ['taxDocumentId' => $taxDocumentId]);

        $note = $this->noteCreateUseCase->__invoke($taxDocumentId);
        $response = $this->noteRepository->send($note);
        $response->name = $note->getName();

        return $response;
    }
}
