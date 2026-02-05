<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Domain;

use App\EInvoicing\Billing\Model\Response\DocumentResponse;
use Brick\Math\Exception\MathException;
use Doctrine\DBAL\Exception;

readonly class NoteSendUseCase
{
    public function __construct(
        private NoteCreateUseCase $noteCreateUseCase,
        private NoteRepositoryInterface $noteRepository,
    ) {
    }

    /**
     * @throws MathException
     * @throws Exception
     */
    public function __invoke(string $taxDocumentId): DocumentResponse
    {
        $note = $this->noteCreateUseCase->__invoke($taxDocumentId);
        $response = $this->noteRepository->send($note);
        $response->name = $note->getName();

        return $response;
    }
}
