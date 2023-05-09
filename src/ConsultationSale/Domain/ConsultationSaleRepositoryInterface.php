<?php

namespace App\ConsultationSale\Domain;

use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface ConsultationSaleRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<ConsultationSale>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?ConsultationSale;

    public function insert(ConsultationSale $consultationSale): ConsultationSale;

    public function update(ConsultationSale $consultationSale): ConsultationSale;

    public function delete(int $id): void;
}