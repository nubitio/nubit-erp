<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentRepository;
use Nubit\Platform\Exception\ServiceException;

/** The series and correlative a credit note over `$related` would get if it were issued now. */
readonly class CreditNoteNumbering
{
    public function __construct(
        private TaxDocumentRepository $taxDocuments,
    ) {}

    /**
     * @return array{series: string, number: string}
     */
    public function next(TaxDocument $related): array
    {
        $branchId = (string) $related->getBranch()?->getId();
        $series = $this->taxDocuments->getTaxDocumentSeriesByBranch($branchId, '07', $related->getSeries());
        if ($series === null || $series->associatedSeries !== $related->getSeries()) {
            throw new ServiceException(sprintf(
                'No hay una serie de nota de crédito configurada para %s.',
                $related->getSeries(),
            ));
        }

        return [
            'series' => $series->series,
            'number' => (string) $this->taxDocuments->getNextSequenceNumber($branchId, '07'),
        ];
    }
}
