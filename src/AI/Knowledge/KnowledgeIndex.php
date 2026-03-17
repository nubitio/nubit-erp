<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Uid\Uuid;

final class KnowledgeIndex
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $docs = $this->collectDocuments($this->projectDir . '/docs');
        $chunks = $this->chunkDocuments($docs);

        if ([] === $chunks) {
            throw new RuntimeException('No documentation found to build the knowledge index.');
        }

        $idf = $this->computeIdf($chunks);
        $indexedChunks = [];
        foreach ($chunks as $chunk) {
            $vector = $this->tfIdf($chunk['tokens'], $idf);
            $indexedChunks[] = [
                'id' => Uuid::v4()->toRfc4122(),
                'source' => $chunk['source'],
                'title' => $chunk['title'],
                'content' => $chunk['content'],
                'vector' => $vector,
            ];
        }

        $index = [
            'idf' => $idf,
            'chunks' => $indexedChunks,
        ];

        $this->persist($index);
        $this->cache = $index;

        return $index;
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if (null !== $this->cache) {
            return $this->cache;
        }

        $path = $this->indexPath();
        if (!file_exists($path)) {
            return $this->build();
        }

        $content = file_get_contents($path);
        if (false === $content) {
            return $this->build();
        }

        $index = json_decode($content, true);
        if (!\is_array($index) || !isset($index['chunks'], $index['idf'])) {
            return $this->build();
        }

        $this->cache = $index;

        return $index;
    }

    public function clear(): void
    {
        $this->cache = null;
        @unlink($this->indexPath());
    }

    private function indexPath(): string
    {
        return $this->projectDir . '/var/knowledge_index.json';
    }

    /**
     * @param array<string, mixed> $index
     */
    private function persist(array $index): void
    {
        $encoded = json_encode($index, JSON_PRETTY_PRINT);
        if (false === $encoded) {
            throw new RuntimeException('Failed to encode knowledge index.');
        }

        file_put_contents($this->indexPath(), $encoded);
    }

    /**
     * @return array<int, array{path: string, content: string}>
     */
    private function collectDocuments(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()->in($directory)->name('*.md');

        $docs = [];
        foreach ($finder as $file) {
            $contents = $file->getContents();
            if ('' === trim($contents)) {
                continue;
            }

            $docs[] = [
                'path' => $file->getRelativePathname(),
                'content' => $contents,
            ];
        }

        return $docs;
    }

    /**
     * @param array<int, array{path: string, content: string}> $docs
     * @return array<int, array{source: string, title: string, content: string, tokens: array<string, int>}>
     */
    private function chunkDocuments(array $docs): array
    {
        $chunks = [];
        foreach ($docs as $doc) {
            $sections = $this->splitByHeadings($doc['content']);
            foreach ($sections as $section) {
                $text = trim($section['text']);
                if ('' === $text) {
                    continue;
                }

                $tokens = $this->tokenize($text);
                if ([] === $tokens) {
                    continue;
                }

                $chunks[] = [
                    'source' => $doc['path'],
                    'title' => $section['title'],
                    'content' => $this->truncate($text),
                    'tokens' => $tokens,
                ];
            }
        }

        return $chunks;
    }

    /**
     * @return array<int, array{title: string, text: string}>
     */
    private function splitByHeadings(string $content): array
    {
        $lines = explode("\n", $content);
        $sections = [];
        $currentTitle = 'Introduction';
        $buffer = [];

        foreach ($lines as $line) {
            if (preg_match('/^##+ (.+)$/', $line, $m)) {
                if ($buffer !== []) {
                    $sections[] = ['title' => $currentTitle, 'text' => trim(implode("\n", $buffer))];
                }

                $currentTitle = $m[1];
                $buffer = [];
            } else {
                $buffer[] = $line;
            }
        }

        if ($buffer !== []) {
            $sections[] = ['title' => $currentTitle, 'text' => trim(implode("\n", $buffer))];
        }

        return $sections;
    }

    /**
     * @param array<int, array{tokens: array<string, int>}> $chunks
     * @return array<string, float>
     */
    private function computeIdf(array $chunks): array
    {
        $docCount = \count($chunks);
        $df = [];
        foreach ($chunks as $chunk) {
            foreach (array_keys($chunk['tokens']) as $token) {
                $df[$token] = ($df[$token] ?? 0) + 1;
            }
        }

        $idf = [];
        foreach ($df as $token => $count) {
            $idf[$token] = log(($docCount + 1) / ($count + 1)) + 1.0;
        }

        return $idf;
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
        $stop = $this->stopWords();
        $counts = [];
        foreach ($parts as $part) {
            if (isset($stop[$part])) {
                continue;
            }
            if (mb_strlen($part) < 3) {
                continue;
            }

            $counts[$part] = ($counts[$part] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<string, bool>
     */
    private function stopWords(): array
    {
        $words = [
            'the', 'and', 'for', 'with', 'from', 'this', 'that',
            'para', 'por', 'con', 'los', 'las', 'una', 'uno', 'del',
            'de', 'la', 'el', 'en', 'un', 'una', 'sin', 'sobre', 'segun',
            'al', 'a', 'lo', 'sus', 'su', 'mis', 'tus', 'como', 'cuando',
            'donde', 'que', 'qué', 'cuál', 'cual', 'cuáles', 'cuales',
            'ser', 'estar', 'haber', 'hay',
        ];
        return array_fill_keys($words, true);
    }

    private function truncate(string $text, int $limit = 1800): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit) . "\n\n... [sección truncada] ...";
    }
}
