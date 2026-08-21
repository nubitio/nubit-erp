<?php

declare(strict_types=1);

namespace App\AI\Platform;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAI-compatible GPT client pointed at api.x.ai.
 *
 * The stock OpenAI ModelClient hardcodes api.openai.com and requires keys
 * starting with "sk-". xAI keys do not, so this client exists instead of
 * configuring the OpenAI platform with a custom base URI.
 */
final class XaiGptModelClient implements ModelClientInterface
{
    private const string BASE_URL = 'https://api.x.ai';

    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter]
        private readonly string $apiKey,
    ) {
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The xAI API key must not be empty.');
        }

        $this->httpClient = $httpClient instanceof EventSourceHttpClient
            ? $httpClient
            : new EventSourceHttpClient($httpClient);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Gpt;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf(
                'Payload must be an array, but a string was given to "%s".',
                self::class,
            ));
        }

        unset($options['cacheRetention']);

        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $schema = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema'];
            $options['text']['format'] = $schema;
            $options['text']['format']['name'] = $schema['name'];
            $options['text']['format']['type'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['type'];

            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        return new RawHttpResult($this->httpClient->request('POST', self::BASE_URL . '/v1/responses', [
            'auth_bearer' => $this->apiKey,
            'json' => array_merge($options, ['model' => $model->getName()], $payload),
        ]));
    }
}
