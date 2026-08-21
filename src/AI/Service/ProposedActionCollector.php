<?php

declare(strict_types=1);

namespace App\AI\Service;

use App\AI\Entity\AssistantProposedAction;

/**
 * Request-scoped bag of actions proposed during the current assistant call.
 * The stream controller emits them as SSE `event: action` after the model text.
 */
final class ProposedActionCollector
{
    /** @var list<AssistantProposedAction> */
    private array $actions = [];

    public function add(AssistantProposedAction $action): void
    {
        $this->actions[] = $action;
    }

    /**
     * @return list<AssistantProposedAction>
     */
    public function all(): array
    {
        return $this->actions;
    }
}
