<?php

declare(strict_types=1);

namespace App\AI\Platform;

use Psr\EventDispatcher\EventDispatcherInterface;
use SensitiveParameter;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\OpenAiContract;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ResultConverter;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Platform;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds the assistant LLM platform: xAI when XAI_API_KEY is set, otherwise
 * the existing OpenAI platform (local/dev fallback).
 */
final readonly class AssistantPlatformFactory
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire('%env(default::XAI_API_KEY)%')]
        #[SensitiveParameter]
        private ?string $xaiApiKey,
        #[Autowire('%env(default::OPENAI_API_KEY)%')]
        #[SensitiveParameter]
        private ?string $openaiApiKey,
    ) {}

    public function create(): Platform
    {
        $xaiApiKey = $this->xaiApiKey ?? '';
        $openaiApiKey = $this->openaiApiKey ?? '';

        if ($xaiApiKey !== '') {
            return new Platform(
                [new XaiGptModelClient($this->httpClient, $xaiApiKey)],
                [new ResultConverter()],
                new XaiModelCatalog(),
                OpenAiContract::create(),
                $this->eventDispatcher,
            );
        }

        $openaiKey = $openaiApiKey !== '' ? $openaiApiKey : 'sk-not-configured';

        return OpenAiPlatformFactory::create($openaiKey, $this->httpClient, eventDispatcher: $this->eventDispatcher);
    }
}
