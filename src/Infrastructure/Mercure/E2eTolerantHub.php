<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;

final class E2eTolerantHub implements HubInterface
{
    public function __construct(
        private readonly HubInterface $inner,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getPublicUrl(): string
    {
        return $this->inner->getPublicUrl();
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->inner->getFactory();
    }

    public function publish(Update $update): string
    {
        try {
            return $this->inner->publish($update);
        } catch (\Throwable $exception) {
            if ((string) ($_SERVER['E2E_SEED_ENABLED'] ?? '') === '1') {
                $this->logger->warning('Mercure publish skipped while E2E mode is enabled.', [
                    'exception' => $exception,
                    'topics' => $update->getTopics(),
                ]);

                return '';
            }

            $this->logger->warning('Mercure publish failed. Real-time updates will be unavailable.', [
                'exception' => $exception,
                'topics' => $update->getTopics(),
            ]);

            return '';
        }
    }
}
