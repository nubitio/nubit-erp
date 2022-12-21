<?php

namespace App\Outbound\Domain;

interface OutboundRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(Outbound $outbound): void;

    public function update(Outbound $outbound): void;

    public function delete(string $id): void;
}