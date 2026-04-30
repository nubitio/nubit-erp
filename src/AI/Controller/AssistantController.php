<?php

declare(strict_types=1);

namespace App\AI\Controller;

use App\AI\Service\SessionContextBuilder;
use Nubit\Platform\Exception\ServiceException;
use App\AI\Dto\AssistantMessageInput;
use App\AI\Dto\AssistantRequest;
use App\AI\Dto\AssistantResponse;
use App\AI\Dto\AttachmentInput;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
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
        $lastUserIndex = null;
        foreach (array_keys($data->messages) as $i) {
            if ('assistant' !== $data->messages[$i]->role) {
                $lastUserIndex = $i;
            }
        }

        [$imageAttachments, $textAttachments] = $this->partitionAttachments($data->attachments);

        $userMessages = [];
        foreach ($data->messages as $i => $message) {
            $isLast = ($i === $lastUserIndex);
            $userMessages[] = $this->convertMessage(
                $message,
                $isLast ? $imageAttachments : [],
                $isLast ? $textAttachments : [],
            );
        }

        $messageBag = new MessageBag(...$userMessages);

        // Prepend a dynamic system message so SystemPromptInputProcessor skips its static file.
        // The full prompt = context block (live session data) + static assistant.md content.
        $messageBag = $messageBag->withSystemMessage(
            Message::forSystem($this->buildDynamicSystemPrompt())
        );

        $this->logger->info('Assistant request received', [
            'messages_count' => \count($data->messages),
            'attachments_count' => \count($data->attachments),
            'message_preview' => array_slice(
                array_map(
                    static fn (AssistantMessageInput $m): string => $m->content,
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
     * Converts an AssistantMessageInput into a platform MessageInterface.
     *
     * For user messages, builds a multi-part content array:
     *   - A Text part for the message body (if non-empty)
     *   - An ImageUrl part for each image attachment (if s3Url is present)
     *   - A Text reference block for each non-image attachment
     *
     * @param AttachmentInput[] $imageAttachments
     * @param AttachmentInput[] $textAttachments
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

        /** @var ContentInterface[] $contentParts */
        $contentParts = [];

        if ('' !== $content) {
            $contentParts[] = new Text($content);
        }

        foreach ($imageAttachments as $attachment) {
            if (null === $attachment->s3Url) {
                continue;
            }
            if ('' === $attachment->s3Url) {
                continue;
            }

            $contentParts[] = new ImageUrl($attachment->s3Url);
        }

        foreach ($textAttachments as $attachment) {
            $name = trim($attachment->name);
            $mimeType = trim($attachment->mimeType);
            if ('' === $name) {
                continue;
            }
            if ('' === $mimeType) {
                continue;
            }

            $urlPart = $attachment->s3Url !== null ? ' — ' . $attachment->s3Url : '';
            $contentParts[] = new Text(sprintf('[Attachment: %s (%s)%s]', $name, $mimeType, $urlPart));
        }

        return Message::ofUser(...$contentParts);
    }

    /**
     * Partitions an attachment list into image attachments and non-image attachments.
     *
     * @param  AttachmentInput[] $attachments
     * @return array{0: AttachmentInput[], 1: AttachmentInput[]}
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
