<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

final readonly class KnowledgeRetriever
{
    public function __construct(
        private KnowledgeIndex $index,
    ) {
    }

    /**
     * @return array<int, array{text: string, score: float}>
     */
    public function search(string $query, int $limit = 3): array
    {
        $index = $this->index->load();
        $idf = $index['idf'] ?? [];
        $chunks = $index['chunks'] ?? [];

        $queryVector = $this->tfIdf($this->tokenize($query), $idf);
        if ([] === $queryVector || [] === $chunks) {
            return [];
        }

        $scored = [];
        foreach ($chunks as $chunk) {
            $score = $this->cosine($queryVector, $chunk['vector'] ?? []);
            if ($score <= 0) {
                continue;
            }

            $scored[] = [
                'score' => $score,
                'text' => sprintf(
                    "%s\n\nFuente: %s",
                    $chunk['title'] . "\n" . $chunk['content'],
                    $chunk['source']
                ),
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * @param array<string, float> $a
     * @param array<string, float> $b
     */
    private function cosine(array $a, array $b): float
    {
        if ([] === $a || [] === $b) {
            return 0.0;
        }

        $dot = 0.0;
        foreach ($a as $token => $weight) {
            if (isset($b[$token])) {
                $dot += $weight * $b[$token];
            }
        }

        $normA = sqrt(array_sum(array_map(static fn ($v): float => $v ** 2, $a))) ?: 1;
        $normB = sqrt(array_sum(array_map(static fn ($v): float => $v ** 2, $b))) ?: 1;

        return $dot / ($normA * $normB);
    }

    /**
     * @param array<string, int> $tokens
     * @param array<string, float> $idf
     * @return array<string, float>
     */
    private function tfIdf(array $tokens, array $idf): array
    {
        $vector = [];
        $total = array_sum($tokens) ?: 1;
        foreach ($tokens as $token => $count) {
            $tf = $count / $total;
            $vector[$token] = $tf * ($idf[$token] ?? 0.0);
        }

        $norm = sqrt(array_sum(array_map(static fn (float $v): float => $v ** 2, $vector))) ?: 1;
        foreach ($vector as $token => $weight) {
            $vector[$token] = $weight / $norm;
        }

        return $vector;
    }

    /**
     * @return array<string, int>
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9áéíóúñü ]/u', ' ', $text) ?? $text;

        $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $counts = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 3) {
                continue;
            }

            $counts[$part] = ($counts[$part] ?? 0) + 1;
        }

        return $counts;
    }
}
