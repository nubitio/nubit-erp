<?php

declare(strict_types=1);

namespace App\Tests\AI\Knowledge;

use App\AI\Knowledge\KnowledgeIndexInterface;
use App\AI\Knowledge\KnowledgeRetriever;
use PHPUnit\Framework\TestCase;

final class KnowledgeRetrieverTest extends TestCase
{
    /**
     * @param array<int, array<string, mixed>> $chunks
     * @param array<string, float> $idf
     */
    private function makeIndex(array $chunks, array $idf = []): KnowledgeIndexInterface
    {
        $index = $this->createStub(KnowledgeIndexInterface::class);
        $index->method('load')->willReturn(['chunks' => $chunks, 'idf' => $idf]);

        return $index;
    }

    /**
     * @param array<string, float> $vector
     * @return array<string, mixed>
     */
    private function makeChunk(string $title, string $content, string $source, array $vector): array
    {
        return [
            'title'   => $title,
            'content' => $content,
            'source'  => $source,
            'vector'  => $vector,
        ];
    }

    // ── Empty / degenerate cases ──────────────────────────────────────────────

    public function testReturnsEmptyForEmptyIndex(): void
    {
        $retriever = new KnowledgeRetriever($this->makeIndex([]));
        self::assertSame([], $retriever->search('ventas'));
    }

    public function testReturnsEmptyWhenQueryProducesNoTokens(): void
    {
        // Single chars are filtered by tokenize (< 3 chars)
        $retriever = new KnowledgeRetriever($this->makeIndex([
            $this->makeChunk('T', 'C', 'src', ['ventas' => 1.0]),
        ], ['ventas' => 1.0]));

        // Query of only single chars → empty token vector → cosine = 0 → no results
        $result = $retriever->search('a b');
        self::assertSame([], $result);
    }

    // ── Ranking by cosine similarity ─────────────────────────────────────────

    public function testHigherSimilarityChunkRanksFirst(): void
    {
        $idf = ['factura' => 2.0, 'venta' => 1.5];

        $chunkA = $this->makeChunk('Facturas', 'Factura electrónica', 'doc-a', [
            'factura' => 0.9,  // high match for "factura"
            'venta'   => 0.1,
        ]);
        $chunkB = $this->makeChunk('Ventas', 'Proceso de venta', 'doc-b', [
            'factura' => 0.1,
            'venta'   => 0.9,
        ]);

        $retriever = new KnowledgeRetriever($this->makeIndex([$chunkB, $chunkA], $idf));

        // Query "factura" should rank chunkA first
        $results = $retriever->search('factura', 2);

        self::assertCount(2, $results);
        self::assertStringContainsString('Facturas', $results[0]['text']);
        self::assertStringContainsString('Ventas', $results[1]['text']);
    }

    public function testReturnsAtMostLimitResults(): void
    {
        $idf = ['producto' => 1.0];
        $chunks = array_map(
            fn (int $i): array => $this->makeChunk("Chunk $i", "producto info $i", "src-$i", ['producto' => 0.5]),
            range(1, 10)
        );

        $retriever = new KnowledgeRetriever($this->makeIndex($chunks, $idf));
        $results = $retriever->search('producto', limit: 3);

        self::assertCount(3, $results);
    }

    public function testResultTextContainsTitleContentAndSource(): void
    {
        $idf = ['inventario' => 2.0];
        $chunk = $this->makeChunk('Inventario', 'Gestión de stock', 'docs/inventory.md', [
            'inventario' => 0.8,
        ]);

        $retriever = new KnowledgeRetriever($this->makeIndex([$chunk], $idf));
        $results = $retriever->search('inventario', 1);

        self::assertCount(1, $results);
        self::assertStringContainsString('Inventario', $results[0]['text']);
        self::assertStringContainsString('Gestión de stock', $results[0]['text']);
        self::assertStringContainsString('docs/inventory.md', $results[0]['text']);
        self::assertGreaterThan(0.0, $results[0]['score']);
    }

    public function testChunksWithZeroCosineSimilarityAreExcluded(): void
    {
        // Chunk has no overlap with query → score = 0 → excluded
        $idf = ['stock' => 1.0, 'factura' => 1.0];
        $chunk = $this->makeChunk('Stock', 'Gestión de stock', 'src', [
            'stock' => 1.0,
        ]);

        $retriever = new KnowledgeRetriever($this->makeIndex([$chunk], $idf));
        // Query "factura" has no overlap with chunk vector
        $results = $retriever->search('factura', 5);

        self::assertSame([], $results);
    }

    // ── Score is numeric and bounded ─────────────────────────────────────────

    public function testScoreIsBetweenZeroAndOne(): void
    {
        $idf = ['venta' => 1.0];
        $chunk = $this->makeChunk('Ventas', 'Total ventas', 'src', ['venta' => 1.0]);

        $retriever = new KnowledgeRetriever($this->makeIndex([$chunk], $idf));
        $results = $retriever->search('venta', 1);

        self::assertCount(1, $results);
        self::assertGreaterThan(0.0, $results[0]['score']);
        self::assertLessThanOrEqual(1.001, $results[0]['score']); // cosine ≤ 1 + float tolerance
    }
}
