<?php

namespace App\Outbound\Domain;

interface OutboundRepositoryInterface
{
    /**
     * @param array<int, array<string, mixed>>|null $filter
     *
     * @return array<string, mixed>
     */
    public function getAll(array $filter = null): array;

    public function insert(Outbound $outbound): void;

    public function update(Outbound $outbound): void;

    public function delete(string $id): void;
}
