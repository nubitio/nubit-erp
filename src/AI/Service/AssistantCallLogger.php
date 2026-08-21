<?php

declare(strict_types=1);

namespace App\AI\Service;

use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Throwable;

/**
 * Structured log line per assistant LLM call (model, tokens, latency, tenant, user).
 */
final readonly class AssistantCallLogger
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $extra
     */
    public function logSuccess(
        ResultInterface $result,
        float $startedAt,
        string $model,
        ?string $user,
        ?string $tenant,
        array $extra = [],
    ): void {
        $this->logger->info('Assistant response generated', array_merge([
            'model' => $model,
            'user' => $user,
            'tenant' => $tenant,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'chars' => $this->contentLength($result),
        ], $this->usageFields($result), $extra));
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function logFailure(
        Throwable $throwable,
        float $startedAt,
        string $model,
        ?string $user,
        ?string $tenant,
        array $extra = [],
    ): void {
        $this->logger->error('Assistant invocation failed', array_merge([
            'model' => $model,
            'user' => $user,
            'tenant' => $tenant,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error' => $throwable->getMessage(),
        ], $extra));
    }

    /**
     * @return array<string, int|null>
     */
    private function usageFields(ResultInterface $result): array
    {
        foreach ($result->getMetadata() as $value) {
            if ($value instanceof TokenUsage) {
                return [
                    'prompt_tokens' => $value->getPromptTokens(),
                    'completion_tokens' => $value->getCompletionTokens(),
                    'total_tokens' => $value->getTotalTokens(),
                ];
            }
        }

        $raw = $result->getRawResult();
        if (!$raw instanceof RawHttpResult) {
            return [];
        }

        try {
            $data = $raw->getData();
        } catch (Throwable) {
            return [];
        }

        if (!\is_array($data) || !isset($data['usage']) || !\is_array($data['usage'])) {
            return [];
        }

        $usage = $data['usage'];

        return [
            'prompt_tokens' => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : (isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null),
            'completion_tokens' => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : (isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null),
            'total_tokens' => isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : null,
        ];
    }

    private function contentLength(ResultInterface $result): int
    {
        $content = $result->getContent();
        if (\is_string($content)) {
            return \strlen($content);
        }

        return 0;
    }
}
