<?php

namespace App\Inbound\Domain;

interface InboundRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(Inbound $inbound): void;

    public function update(Inbound $inbound): void;

    public function delete(string $id): void;
}
