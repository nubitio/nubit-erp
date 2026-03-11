<?php

declare(strict_types=1);

namespace App\Tests\AI;

use PHPUnit\Framework\TestCase;

/**
 * AC-AI1-01: Every tool name listed in prompts/assistant.md must correspond to a real
 * #[AsTool]-annotated method under src/AI/Tool/.
 *
 * This test:
 *   1. Scans all PHP files under src/AI/Tool/ and collects registered tool names from #[AsTool].
 *   2. Parses prompts/assistant.md and extracts the "Allowed tools" list.
 *   3. Asserts that every tool name in the prompt exists in the scanned #[AsTool] set.
 */
final class ToolCoherenceTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        // Walk up from this file to find the project root (composer.json is there).
        $dir = __DIR__;
        while ($dir !== '/' && !file_exists($dir . '/composer.json')) {
            $dir = dirname($dir);
        }
        $this->projectDir = $dir;
    }

    public function testAllPromptToolsHaveAnAsToolImplementation(): void
    {
        $registeredTools = $this->collectRegisteredToolNames();
        $promptTools = $this->extractPromptToolNames();

        self::assertNotEmpty($registeredTools, 'Expected at least one #[AsTool] in src/AI/Tool/');
        self::assertNotEmpty($promptTools, 'Expected at least one tool in the "Allowed tools" section of prompts/assistant.md');

        foreach ($promptTools as $toolName) {
            self::assertContains(
                $toolName,
                $registeredTools,
                sprintf(
                    'Tool "%s" is listed in prompts/assistant.md but has no #[AsTool] implementation in src/AI/Tool/. '
                    . 'Registered tools: [%s]',
                    $toolName,
                    implode(', ', $registeredTools)
                )
            );
        }
    }

    public function testAllAsToolImplementationsAreMentionedInPrompt(): void
    {
        $registeredTools = $this->collectRegisteredToolNames();
        $promptTools = $this->extractPromptToolNames();

        foreach ($registeredTools as $toolName) {
            self::assertContains(
                $toolName,
                $promptTools,
                sprintf(
                    'Tool "%s" has an #[AsTool] implementation but is NOT listed in the "Allowed tools" '
                    . 'section of prompts/assistant.md. '
                    . 'Prompt tools: [%s]',
                    $toolName,
                    implode(', ', $promptTools)
                )
            );
        }
    }

    /**
     * Scan src/AI/Tool/**  *.php and collect all tool names from:
     *   - #[AsTool(name: 'foo', ...)]
     *   - #[AsTool('foo', ...)]
     *
     * @return string[]
     */
    private function collectRegisteredToolNames(): array
    {
        $toolDir = $this->projectDir . '/src/AI/Tool';
        self::assertDirectoryExists($toolDir, 'src/AI/Tool directory must exist');

        $phpFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($toolDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $toolNames = [];

        /** @var \SplFileInfo $file */
        foreach ($phpFiles as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if ($source === false) {
                continue;
            }

            // Match explicit name: parameter → #[AsTool(name: 'foo', ...)] or #[AsTool(name: "foo", ...)]
            if (preg_match_all('/#\[AsTool\([^)]*name:\s*[\'"]([^\'"]+)[\'"]/', $source, $matches)) {
                foreach ($matches[1] as $name) {
                    $toolNames[] = $name;
                }
                // A file may mix explicit name: and positional, so also check positional below.
            }

            // Match positional first argument → #[AsTool('foo', ...)] or #[AsTool("foo", ...)]
            // Only when there is no explicit name: parameter already captured above.
            if (preg_match_all('/#\[AsTool\(\s*[\'"]([^\'"]+)[\'"]/', $source, $posMatches)) {
                foreach ($posMatches[1] as $name) {
                    // Avoid double-counting names already found via name: parameter.
                    if (!in_array($name, $toolNames, true)) {
                        $toolNames[] = $name;
                    }
                }
            }
        }

        return array_unique($toolNames);
    }

    /**
     * Parse prompts/assistant.md and extract tool names from the "Allowed tools:" section.
     * Supports both bare identifiers and backtick-quoted identifiers:
     *   * `tool_name`
     *   * tool_name
     *
     * @return string[]
     */
    private function extractPromptToolNames(): array
    {
        $promptFile = $this->projectDir . '/prompts/assistant.md';
        self::assertFileExists($promptFile, 'prompts/assistant.md must exist');

        $content = file_get_contents($promptFile);
        self::assertIsString($content);

        // Find the "Allowed tools:" section and stop at the next "---" or "##".
        if (!preg_match('/Allowed tools:\s*\n(.*?)(?:\n---|\n##|\Z)/s', $content, $sectionMatch)) {
            return [];
        }

        $section = $sectionMatch[1];

        // Extract tool names: either `name` or * name or - name (with optional backticks).
        preg_match_all('/[*\-]\s+`?([a-z_][a-z0-9_]*)`?/', $section, $nameMatches);

        return array_values(array_unique($nameMatches[1]));
    }
}
