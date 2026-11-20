<?php

declare(strict_types=1);

namespace App\AI\Service;

use DateTimeImmutable;
use DateTimeZone;
use Nubit\Platform\Cache\CacheManager;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Enforces a monthly token cap from plan_features.ai_assistant.config_json.monthlyTokenCap.
 * 0 or missing cap means unlimited. Cache failures fail open (assistant still works).
 */
final readonly class AssistantTokenQuotaService
{
    private const string CACHE_PREFIX = 'assistant.tokens.';

    private const int UNKNOWN_USAGE_FLOOR = 800;

    public function __construct(
        private FeatureCheckerInterface $featureChecker,
        private CacheManager $cacheManager,
        private LoggerInterface $logger,
    ) {}

    public function assertWithinQuota(): void
    {
        $cap = $this->monthlyCap();
        if ($cap <= 0) {
            return;
        }

        $used = $this->usedThisMonth();
        if ($used >= $cap) {
            throw ServiceException::create(
                'Alcanzaste el límite mensual de tokens del asistente. Actualiza tu plan o espera al próximo mes.',
                Response::HTTP_PAYMENT_REQUIRED,
            );
        }
    }

    public function recordUsage(ResultInterface $result): void
    {
        $cap = $this->monthlyCap();
        if ($cap <= 0) {
            return;
        }

        $tokens = $this->tokensFromResult($result) ?? self::UNKNOWN_USAGE_FLOOR;
        $this->add($tokens);
    }

    public function monthlyCap(): int
    {
        $config = $this->featureChecker->getFeatureConfig('ai_assistant');
        $cap = $config['monthlyTokenCap'] ?? 0;

        return is_numeric($cap) ? max(0, (int) $cap) : 0;
    }

    public function usedThisMonth(): int
    {
        try {
            $value = $this->cacheManager->get($this->cacheKey());
        } catch (Throwable $throwable) {
            $this->logger->warning('Assistant token quota read failed', [
                'error' => $throwable->getMessage(),
            ]);

            return 0;
        }

        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    private function add(int $tokens): void
    {
        try {
            $used = $this->usedThisMonth() + max(0, $tokens);
            $this->cacheManager->set($this->cacheKey(), $used, $this->ttlUntilNextMonth());
        } catch (Throwable $throwable) {
            $this->logger->warning('Assistant token quota write failed', [
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function cacheKey(): string
    {
        $month = new DateTimeImmutable('now', new DateTimeZone('America/Lima'))->format('Ym');

        return self::CACHE_PREFIX . $month;
    }

    private function ttlUntilNextMonth(): int
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
        $next = $now->modify('first day of next month')->setTime(0, 0);

        return max(60, $next->getTimestamp() - $now->getTimestamp());
    }

    private function tokensFromResult(ResultInterface $result): ?int
    {
        foreach ($result->getMetadata() as $value) {
            if ($value instanceof TokenUsage) {
                return $value->getTotalTokens();
            }
        }

        return null;
    }
}
