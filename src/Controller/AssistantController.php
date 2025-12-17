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

        $content = $result->getContent();
        if (!\is_string($content)) {
            $this->logger->warning('Assistant response is not text', [
                'type' => gettype($content),
                'content' => $content,
            ]);

            throw ServiceException::create('La respuesta del asistente no es válida.');
        }

        $this->logger->info('Assistant response generated', [
            'chars' => \strlen($content),
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
}
