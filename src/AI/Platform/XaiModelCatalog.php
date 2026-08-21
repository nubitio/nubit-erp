<?php

declare(strict_types=1);

namespace App\AI\Platform;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Grok models exposed through xAI's OpenAI-compatible Responses API.
 *
 * Mapped onto the OpenAI GPT model class so the existing request/result
 * converters can be reused.
 */
final class XaiModelCatalog extends AbstractModelCatalog
{
    public function __construct()
    {
        $chatCapabilities = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::TOOL_CALLING,
            Capability::INPUT_IMAGE,
        ];

        $this->models = [
            'grok-4.3' => ['class' => Gpt::class, 'capabilities' => $chatCapabilities],
            'grok-4.5' => ['class' => Gpt::class, 'capabilities' => $chatCapabilities],
            'grok-4.6' => ['class' => Gpt::class, 'capabilities' => $chatCapabilities],
            'grok-4.20-0309-non-reasoning' => ['class' => Gpt::class, 'capabilities' => $chatCapabilities],
            'grok-4.20-0309-reasoning' => ['class' => Gpt::class, 'capabilities' => $chatCapabilities],
            'gpt-4o-mini' => ['class' => Gpt::class, 'capabilities' => $chatCapabilities],
        ];
    }
}
