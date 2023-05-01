<?php

namespace App\Consultation\Domain;

use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface ConsultationRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Consultation>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Consultation;

    public function insert(Consultation $consultation): Consultation;

    public function update(Consultation $consultation): Consultation;

    public function delete(int $id): void;
}