<?php

declare(strict_types=1);

namespace App\AI\Controller;

use App\AI\Dto\AssistantMessageInput;
use App\AI\Dto\AssistantRequest;
use App\AI\Service\SessionContextBuilder;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

/**
 * Streams the AI assistant response via Server-Sent Events (SSE).
 *
 * The client connects to POST /api/assistant/stream and receives incremental
 * delta tokens as they are generated, eliminating the perceived wait time of
 * the blocking POST /api/assistant endpoint.
 *
 * SSE format:
 *   data: {"delta": "token text"}\n\n
 *   data: [DONE]\n\n
 *   data: {"error": "message"}\n\n   (on failure)
 */
#[Route('/api/assistant/stream', name: 'api_assistant_stream', methods: ['POST'])]
class AssistantStreamController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'ai.agent.default')]
        private readonly AgentInterface $agent,
        private readonly LoggerInterface $logger,
        private readonly SessionContextBuilder $sessionContextBuilder,
        private readonly SerializerInterface $serializer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly RateLimiterFactoryInterface $assistantLimiter,
    ) {}

    public function __invoke(Request $request): Response
    {
        $limiter = $this->assistantLimiter->create($this->getUser()?->getUserIdentifier() ?? 'anonymous');
        if (!$limiter->consume()->isAccepted()) {
            return $this->json([
                'error' => 'Demasiadas solicitudes al asistente. Espera un momento antes de volver a intentar.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        /** @var AssistantRequest $data */
        $data = $this->serializer->deserialize($request->getContent(), AssistantRequest::class, 'json');

        // Build the message bag eagerly (no I/O, just DTO mapping)
        $messageBag = $this->buildMessageBag($data);

        $logger = $this->logger;
        $agent = $this->agent;

        return new StreamedResponse(
            function () use ($messageBag, $data, $agent, $logger): void {
                // Disable output buffering so tokens reach the client immediately
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }

                $logger->info('Assistant stream request received', [
                    'messages_count' => \count($data->messages),
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);

                try {
                    $result = $agent->call($messageBag, ['stream' => true]);

                    if ($result instanceof StreamResult) {
                        foreach ($result->getContent() as $chunk) {
                            $content = $chunk instanceof \Stringable
                                ? (string) $chunk
                                : (is_string($chunk) ? $chunk : '');
                            if ($content === '') {
                                continue;
                            }

                            $this->sendEvent(['delta' => $content]);
                        }
                    } else {
                        // Fallback: non-streaming result — send as a single chunk
                        $content = $result->getContent();
                        if (\is_string($content) && $content !== '') {
                            $this->sendEvent(['delta' => $content]);
                        }
                    }

                    $this->sendRaw('[DONE]');
                } catch (Throwable $throwable) {
                    $logger->error('Assistant stream failed', ['error' => $throwable->getMessage()]);
                    $this->sendEvent(['error' => 'No se pudo generar la respuesta del asistente.']);
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-store',
                'X-Accel-Buffering' => 'no', // Disable Nginx/Caddy proxy buffering
                'Connection' => 'keep-alive',
            ],
        );
    }

    /**
     * Sends a structured SSE data event.
     *
     * @param array<string, string> $payload
     */
    private function sendEvent(array $payload): void
    {
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }

    private function sendRaw(string $data): void
    {
        echo 'data: ' . $data . "\n\n";
        flush();
    }

    private function buildMessageBag(AssistantRequest $data): MessageBag
    {
        $lastUserIndex = null;
        foreach (array_keys($data->messages) as $i) {
            if ('assistant' !== $data->messages[$i]->role) {
                $lastUserIndex = $i;
            }
        }

        [$imageAttachments, $textAttachments] = $this->partitionAttachments($data->attachments);

        $userMessages = [];
        foreach ($data->messages as $i => $message) {
            $isLast = $i === $lastUserIndex;
            $userMessages[] = $this->convertMessage(
                $message,
                $isLast ? $imageAttachments : [],
                $isLast ? $textAttachments : [],
            );
        }

        $messageBag = new MessageBag(...$userMessages);

        return $messageBag->withSystemMessage(Message::forSystem($this->buildDynamicSystemPrompt($data)));
    }

    private function buildDynamicSystemPrompt(AssistantRequest $data): string
    {
        $contextBlock = $this->sessionContextBuilder->build();

        // Inject screen context if provided
        $screenBlock = '';
        if ($data->screenContext !== null) {
            $screenBlock =
                "\n## Pantalla activa del usuario\n"
                . sprintf('- Screen: %s', $data->screenContext->screen ?? 'unknown')
                . "\n"
                . (
                    $data->screenContext->entityType !== null
                        ? sprintf('- Tipo de entidad: %s', $data->screenContext->entityType) . "\n"
                        : ''
                )
                . (
                    $data->screenContext->entityId !== null
                        ? sprintf('- ID de entidad: %s', $data->screenContext->entityId) . "\n"
                        : ''
                );
        }

        $staticPromptPath = $this->projectDir . '/prompts/assistant.md';
        $staticPrompt = is_file($staticPromptPath) ? (string) file_get_contents($staticPromptPath) : '';

        return $contextBlock . $screenBlock . "\n\n---\n\n" . $staticPrompt;
    }

    /**
     * @param \App\AI\Dto\AttachmentInput[] $attachments
     * @return array{0: \App\AI\Dto\AttachmentInput[], 1: \App\AI\Dto\AttachmentInput[]}
     */
    private function partitionAttachments(array $attachments): array
    {
        $images = [];
        $texts = [];
        foreach ($attachments as $a) {
            if (str_starts_with(trim($a->mimeType), 'image/')) {
                $images[] = $a;
            } else {
                $texts[] = $a;
            }
        }

        return [$images, $texts];
    }

    /**
     * @param \App\AI\Dto\AttachmentInput[] $imageAttachments
     * @param \App\AI\Dto\AttachmentInput[] $textAttachments
     */
    private function convertMessage(
        AssistantMessageInput $message,
        array $imageAttachments = [],
        array $textAttachments = [],
    ): MessageInterface {
        $content = trim($message->content);

        if ('assistant' === $message->role) {
            return Message::ofAssistant($content);
        }

        $contentParts = [];

        if ('' !== $content) {
            $contentParts[] = new Text($content);
        }

        foreach ($imageAttachments as $attachment) {
            if (null === $attachment->s3Url || '' === $attachment->s3Url) {
                continue;
            }
            $contentParts[] = new ImageUrl($attachment->s3Url);
        }

        foreach ($textAttachments as $attachment) {
            $name = trim($attachment->name);
            $mimeType = trim($attachment->mimeType);
            if ('' === $name || '' === $mimeType) {
                continue;
            }
            $urlPart = $attachment->s3Url !== null ? ' — ' . $attachment->s3Url : '';
            $contentParts[] = new Text(sprintf('[Attachment: %s (%s)%s]', $name, $mimeType, $urlPart));
        }

        return Message::ofUser(...$contentParts);
    }
}
