<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Exception\ServiceException;
use App\Dto\Assistant\AssistantRequest;
use App\Dto\Assistant\AssistantResponse;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Throwable;

class AssistantController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'ai.agent.default')]
        private readonly AgentInterface $agent,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AssistantRequest $data): AssistantResponse
    {
        $messageBag = new MessageBag(...array_map($this->convertMessage(...), $data->messages));

        $this->logger->info('Assistant request received', [
            'messages_count' => \count($data->messages),
            'message_preview' => array_slice(array_map(static fn ($m) => $m['content'] ?? '', $data->messages), 0, 3),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $result = $this->agent->call($messageBag);
        } catch (Throwable $throwable) {
            $this->logger->error('Assistant invocation failed', [
                'error' => $throwable->getMessage(),
            ]);

            throw ServiceException::create(
                'No se pudo generar la respuesta del asistente.',
                Response::HTTP_BAD_REQUEST,
                $throwable
            );
        }

        $content = $this->normalizeContent($result->getContent());
        if ('' === $content) {
            $this->logger->warning('Assistant response is not text', [
                'type' => gettype($result->getContent()),
                'content' => $result->getContent(),
            ]);

            throw ServiceException::create('La respuesta del asistente no es válida.');
        }

        $this->logger->info('Assistant response generated', [
            'chars' => \strlen($content),
            'preview' => mb_substr($content, 0, 120),
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        return new AssistantResponse($content);
    }

    private function convertMessage(mixed $message): MessageInterface
    {
        $role = \is_array($message) ? ($message['role'] ?? '') : ($message->role ?? '');
        $content = \is_array($message) ? ($message['content'] ?? '') : ($message->content ?? '');
        $content = trim((string) $content);

        if ('assistant' === $role) {
            return Message::ofAssistant($content);
        }

        return Message::ofUser($content);
    }

    private function normalizeContent(mixed $content): string
    {
        if (\is_string($content)) {
            return trim($content);
        }

        if (\is_iterable($content)) {
            $parts = [];
            foreach ($content as $item) {
                if (\is_string($item)) {
                    $parts[] = $item;
                    continue;
                }

                if (\is_object($item) && method_exists($item, 'getContent')) {
                    $normalized = $item->getContent();
                    if (\is_string($normalized)) {
                        $parts[] = $normalized;
                    }
                }
            }

            return trim(implode("\n\n", array_filter($parts)));
        }

        if (\is_object($content) && method_exists($content, 'getContent')) {
            $normalized = $content->getContent();

            return \is_string($normalized) ? trim($normalized) : '';
        }

        return '';
    }
}
