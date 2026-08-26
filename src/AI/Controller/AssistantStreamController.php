<?php

declare(strict_types=1);

namespace App\AI\Controller;

use App\AI\Dto\AssistantMessageInput;
use App\AI\Dto\AssistantRequest;
use App\AI\Service\AssistantCallLogger;
use App\AI\Service\AssistantPromptAssembler;
use App\AI\Service\AssistantThreadService;
use App\AI\Service\AssistantTokenQuotaService;
use App\AI\Service\ProposedActionCollector;
use App\Identity\Entity\User;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Nubit\Platform\Tenant\Context\TenantContext;
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
#[RequiresFeature('ai_assistant')]
#[Route('/api/assistant/stream', name: 'api_assistant_stream', methods: ['POST'])]
class AssistantStreamController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'ai.agent.default')]
        private readonly AgentInterface $agent,
        private readonly LoggerInterface $logger,
        private readonly AssistantPromptAssembler $promptAssembler,
        private readonly SerializerInterface $serializer,
        private readonly AssistantCallLogger $callLogger,
        private readonly ProposedActionCollector $proposedActionCollector,
        private readonly AssistantThreadService $threadService,
        private readonly AssistantTokenQuotaService $tokenQuota,
        private readonly TenantContext $tenantContext,
        private readonly RateLimiterFactoryInterface $assistantLimiter,
        #[Autowire('%env(default:ai_model_chat_default:AI_MODEL_CHAT)%')]
        private readonly string $model,
    ) {}

    public function __invoke(Request $request): Response
    {
        $limiter = $this->assistantLimiter->create($this->getUser()?->getUserIdentifier() ?? 'anonymous');
        if (!$limiter->consume()->isAccepted()) {
            return $this->json([
                'error' => 'Demasiadas solicitudes al asistente. Espera un momento antes de volver a intentar.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $this->tokenQuota->assertWithinQuota();
        } catch (ServiceException $exception) {
            return $this->json(
                ['error' => $exception->getMessage()],
                $exception->getCode() ?: Response::HTTP_PAYMENT_REQUIRED,
            );
        }

        /** @var AssistantRequest $data */
        $data = $this->serializer->deserialize($request->getContent(), AssistantRequest::class, 'json');

        // Build the message bag eagerly (no I/O, just DTO mapping)
        $messageBag = $this->buildMessageBag($data);

        $logger = $this->logger;
        $agent = $this->agent;
        $callLogger = $this->callLogger;
        $collector = $this->proposedActionCollector;
        $threadService = $this->threadService;
        $tokenQuota = $this->tokenQuota;
        $authenticatedUser = $this->getUser();
        $user = $authenticatedUser?->getUserIdentifier();
        $tenant = $this->tenantContext->getTenantName();
        $model = $this->model;

        return new StreamedResponse(
            function () use (
                $messageBag,
                $data,
                $agent,
                $logger,
                $callLogger,
                $collector,
                $threadService,
                $tokenQuota,
                $authenticatedUser,
                $user,
                $tenant,
                $model,
            ): void {
                // Disable output buffering so tokens reach the client immediately
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }

                $startedAt = microtime(true);
                $logger->info('Assistant stream request received', [
                    'messages_count' => \count($data->messages),
                    'model' => $model,
                    'user' => $user,
                    'tenant' => $tenant,
                ]);

                try {
                    $result = $agent->call($messageBag, ['stream' => true]);
                    $assistantText = '';

                    if ($result instanceof StreamResult) {
                        foreach ($result->getContent() as $chunk) {
                            $content = $chunk instanceof \Stringable
                                ? (string) $chunk
                                : (is_string($chunk) ? $chunk : '');
                            if ($content === '') {
                                continue;
                            }

                            $assistantText .= $content;
                            $this->sendEvent(['delta' => $content]);
                        }
                    } else {
                        // Fallback: non-streaming result — send as a single chunk
                        $content = $result->getContent();
                        if (\is_string($content) && $content !== '') {
                            $assistantText = $content;
                            $this->sendEvent(['delta' => $content]);
                        }
                    }

                    $callLogger->logSuccess($result, $startedAt, $model, $user, $tenant, ['stream' => true]);
                    $tokenQuota->recordUsage($result);
                    $actions = $collector->all();
                    foreach ($actions as $action) {
                        echo "event: action\n";
                        echo 'data: ' . json_encode($action->toCard(), JSON_UNESCAPED_UNICODE) . "\n\n";
                        flush();
                    }
                    if ($authenticatedUser instanceof User) {
                        $threadService->persistTurn($authenticatedUser, $data->messages, $assistantText, $actions);
                    }
                    $this->sendRaw('[DONE]');
                } catch (Throwable $throwable) {
                    $callLogger->logFailure($throwable, $startedAt, $model, $user, $tenant, ['stream' => true]);
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

        return $messageBag->withSystemMessage(Message::forSystem($this->promptAssembler->assemble($data)));
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
