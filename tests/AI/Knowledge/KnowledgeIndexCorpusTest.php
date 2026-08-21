<?php

declare(strict_types=1);

namespace App\Tests\AI\Knowledge;

use App\AI\Knowledge\KnowledgeIndex;
use PHPUnit\Framework\TestCase;

final class KnowledgeIndexCorpusTest extends TestCase
{
    public function testIndexesOnlyTheCashierUserManual(): void
    {
        $root = sys_get_temp_dir() . '/efact-knowledge-' . uniqid('', true);
        mkdir($root . '/docs/adr', 0777, true);
        mkdir($root . '/var', 0777, true);

        file_put_contents(
            $root . '/docs/cashier-user-manual.md',
            "## Abrir caja\n\nPara abrir la caja cuenta el efectivo inicial y confirma.\n",
        );
        file_put_contents(
            $root . '/docs/adr/secret.md',
            "## Interno\n\nTenantConnection switches the database name per request.\n",
        );

        $index = new KnowledgeIndex($root);
        $built = $index->build();

        $sources = array_values(array_unique(array_column($built['chunks'], 'source')));
        self::assertSame(['cashier-user-manual.md'], $sources);

        $encoded = json_encode($built, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('TenantConnection', $encoded);
        self::assertStringContainsString('caja', $encoded);
    }

    public function testBuildFailsWhenManualIsMissing(): void
    {
        $root = sys_get_temp_dir() . '/efact-knowledge-empty-' . uniqid('', true);
        mkdir($root . '/docs', 0777, true);
        mkdir($root . '/var', 0777, true);

        $this->expectException(\RuntimeException::class);
        new KnowledgeIndex($root)->build();
    }
}
