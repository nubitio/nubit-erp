<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

interface KnowledgeIndexInterface
{
    /** @return array{chunks: array<int, array<string, mixed>>, idf: array<string, float>} */
    public function load(): array;
}
