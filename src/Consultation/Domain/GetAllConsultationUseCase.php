<?php

namespace App\Consultation\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllConsultationUseCase
{
    public function __construct(
        private ConsultationRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Consultation>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
