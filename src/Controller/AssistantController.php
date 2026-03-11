<?php

declare(strict_types=1);

namespace App\Controller;

use App\AI\Service\SessionContextBuilder;
use App\Core\Exception\ServiceException;
use App\Dto\Assistant\AssistantRequest;
use App\Dto\Assistant\AssistantResponse;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AssistantController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'ai.agent.default')]
        private readonly AgentInterface $agent,
        private readonly LoggerInterface $logger,
        private readonly SessionContextBuilder $sessionContextBuilder,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(AssistantRequest $data): AssistantResponse
    {
        $userMessages = array_map($this->convertMessage(...), $data->messages);
        $messageBag = new MessageBag(...$userMessages);

        // Prepend a dynamic system message so SystemPromptInputProcessor skips its static file.
        // The full prompt = context block (live session data) + static assistant.md content.
        $messageBag = $messageBag->withSystemMessage(
            Message::forSystem($this->buildDynamicSystemPrompt())
        );

        $this->logger->info('Assistant request received', [
            'messages_count' => \count($data->messages),
            'message_preview' => array_slice(
                array_map(
                    static fn ($m) => $m->content,
                    $data->messages
                ),
                0,
                3
            ),
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

    /**
     * Builds the full dynamic system prompt: session context block + static assistant.md content.
     *
     * Injecting it here causes symfony/ai-bundle's SystemPromptInputProcessor to skip its own
     * static file injection (it bails out when a system message already exists in the bag).
     */
    private function buildDynamicSystemPrompt(): string
    {
        $contextBlock = $this->sessionContextBuilder->build();

        $staticPromptPath = $this->projectDir . '/prompts/assistant.md';
        $staticPrompt = is_file($staticPromptPath)
            ? (string) file_get_contents($staticPromptPath)
            : '';

        return $contextBlock . "\n\n---\n\n" . $staticPrompt;
    }

    private function convertMessage(mixed $message): MessageInterface
    {
        $role = \is_array($message) ? ($message['role'] ?? '') : ($message->role ?? '');
        $content = \is_array($message) ? ($message['content'] ?? '') : ($message->content ?? '');
        $content = trim((string)$content);

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
