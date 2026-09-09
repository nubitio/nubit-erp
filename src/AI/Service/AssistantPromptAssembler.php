<?php

declare(strict_types=1);

namespace App\AI\Service;

use App\AI\Dto\AssistantRequest;
use App\Settings\Runtime\TenantRuntimeConfigProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Builds the per-request system prompt: session + tenant runtime + screen + static file.
 */
final readonly class AssistantPromptAssembler
{
    private const int EXTRA_JSON_MAX_BYTES = 2000;

    public function __construct(
        private SessionContextBuilder $sessionContextBuilder,
        private TenantRuntimeConfigProvider $runtimeConfigProvider,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    public function assemble(AssistantRequest $data): string
    {
        $staticPromptPath = $this->projectDir . '/prompts/assistant.md';
        $staticPrompt = is_file($staticPromptPath) ? (string) file_get_contents($staticPromptPath) : '';

        return (
            $this->sessionContextBuilder->build()
            . $this->runtimeBlock()
            . $this->screenBlock($data)
            . "\n\n---\n\n"
            . $staticPrompt
        );
    }

    private function runtimeBlock(): string
    {
        try {
            $config = $this->runtimeConfigProvider->getConfig();
        } catch (Throwable) {
            return '';
        }

        $profile = is_array($config['profile'] ?? null) ? $config['profile'] : [];
        $capabilities = is_array($config['capabilities'] ?? null) ? $config['capabilities'] : [];
        $ui = is_array($config['ui'] ?? null) ? $config['ui'] : [];
        $inventoryUi = is_array($ui['inventory'] ?? null) ? $ui['inventory'] : [];

        $enabled = [];
        foreach ($capabilities as $key => $capability) {
            if (!(is_array($capability) && ($capability['enabled'] ?? false) === true)) {
                continue;
            }

            $enabled[] = (string) $key;
        }

        $simpleStore = ($profile['simpleStoreMode'] ?? false) === true;
        $showWarehouses = ($inventoryUi['showWarehouseManagement'] ?? false) === true;

        return implode("\n", [
            '',
            '## Configuración del tenant',
            sprintf('- Vertical: %s', (string) ($profile['vertical'] ?? 'custom')),
            sprintf('- Tienda única: %s', $simpleStore ? 'sí' : 'no'),
            sprintf('- Nivel de navegación: %s', (string) ($profile['navigationTier'] ?? 'basic')),
            sprintf('- Gestión de almacenes visible: %s', $showWarehouses ? 'sí' : 'no'),
            sprintf('- Capacidades activas: %s', $enabled === [] ? 'ninguna' : implode(', ', $enabled)),
            $simpleStore ? '- No pidas al usuario que elija sucursal ni almacén.' : '',
        ]);
    }

    private function screenBlock(AssistantRequest $data): string
    {
        if ($data->screenContext === null) {
            return '';
        }

        $ctx = $data->screenContext;
        $lines = [
            '',
            '## Pantalla activa del usuario',
            sprintf('- Screen: %s', $ctx->screen ?? 'unknown'),
        ];

        if ($ctx->entityType !== null && $ctx->entityType !== '') {
            $lines[] = sprintf('- Tipo de entidad: %s', $ctx->entityType);
        }
        if ($ctx->entityId !== null && $ctx->entityId !== '') {
            $lines[] = sprintf('- ID de entidad: %s', $ctx->entityId);
        }

        if (is_array($ctx->extra) && $ctx->extra !== []) {
            $encoded = json_encode($ctx->extra, JSON_UNESCAPED_UNICODE);
            if (is_string($encoded) && $encoded !== '') {
                if (strlen($encoded) > self::EXTRA_JSON_MAX_BYTES) {
                    $encoded = substr($encoded, 0, self::EXTRA_JSON_MAX_BYTES) . '…';
                }
                $lines[] = '- Contexto extra: ' . $encoded;
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
