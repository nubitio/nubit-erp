<?php

declare(strict_types=1);

namespace App\Tests\AI\Eval;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contract eval: each golden Spanish prompt names the tools a competent
 * assistant must have available. This does not call the LLM; it fails if a
 * tool is removed from src/AI/Tool or the system prompt.
 */
final class GoldenConversationCatalogTest extends TestCase
{
    /**
     * @return list<array{0: string, 1: list<string>}>
     */
    public static function goldenPrompts(): array
    {
        return [
            ['¿Cuánto vendí hoy?', ['sales_summary']],
            ['Stock de coca cola', ['search_products', 'product_stock']],
            ['¿Cómo cierro caja?', ['search_manual', 'cash_register_status']],
            ['Crea un cliente con RUC 20100070970', ['propose_customer']],
            ['Crea el producto Pan francés a 0.50', ['propose_product']],
            ['Cobra 2 coca cola en boleta', ['search_products', 'propose_sale']],
            ['¿Hay errores SUNAT recientes?', ['sunat_status']],
            ['¿Qué productos están por agotarse?', ['stock_alert']],
            ['Historial del cliente 20100070970', ['customer_intelligence']],
            ['Estado de la factura F001-12', ['tax_document_status']],
            ['Compras de la última semana', ['purchase_summary', 'recent_purchases']],
            ['Cotizaciones de Juan', ['quote_search']],
            ['Facturas de proveedores vencidas', ['accounts_payable_aging']],
            ['Kardex del producto X', ['inventory_movements']],
            ['Últimas ventas', ['recent_sales']],
            ['Reemplaza la factura F001-12 rechazada', ['tax_document_status', 'propose_replace_rejected']],
            ['Anula la factura F001-12', ['tax_document_status', 'propose_credit_note']],
            ['Crea este producto de la foto', ['propose_product_from_image']],
            ['Completa el RUC 20100070970 en el onboarding', ['propose_onboarding_fill']],
        ];
    }

    /**
     * @param list<string> $expectedTools
     */
    #[DataProvider('goldenPrompts')]
    public function testExpectedToolsAreRegisteredAndDocumented(string $prompt, array $expectedTools): void
    {
        $projectDir = dirname(__DIR__, 3);
        $promptFile = (string) file_get_contents($projectDir . '/prompts/assistant.md');
        self::assertNotFalse($promptFile);

        foreach ($expectedTools as $tool) {
            self::assertStringContainsString(
                '`' . $tool . '`',
                $promptFile,
                sprintf('Prompt "%s" expects tool %s in prompts/assistant.md', $prompt, $tool),
            );
            self::assertTrue(
                $this->toolIsImplemented($projectDir, $tool),
                sprintf('Prompt "%s" expects implemented tool %s', $prompt, $tool),
            );
        }
    }

    private function toolIsImplemented(string $projectDir, string $toolName): bool
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($projectDir . '/src/AI/Tool'));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (
                str_contains($source, "name: '" . $toolName . "'") || str_contains($source, 'name: "' . $toolName . '"')
            ) {
                return true;
            }
        }

        return false;
    }
}
