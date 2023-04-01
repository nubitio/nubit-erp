<?php

namespace App\Inbound\Domain;

interface InboundRepositoryInterface
{
    /**
     * @param array<int, mixed>|null $filter
     * @return array<string, mixed>
     */
    public function getAll(?array $filter = null): array;

    public function insert(Inbound $inbound): void;

    public function update(Inbound $inbound): void;

    public function delete(string $id): void;
}
