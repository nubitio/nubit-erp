<?php

declare(strict_types=1);

namespace App\AI\Service;

use App\AI\Dto\AssistantMessageInput;
use App\AI\Entity\AssistantProposedAction;
use App\AI\Entity\AssistantThread;
use App\AI\Entity\AssistantThreadMessage;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class AssistantThreadService
{
    public const int HISTORY_LIMIT = 20;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function currentOpenThread(User $user): AssistantThread
    {
        $existing = $this->findLatestOpen($user);
        if ($existing instanceof AssistantThread) {
            return $existing;
        }

        $created = $this->createOpen($user);
        $this->entityManager->flush();

        return $created;
    }

    public function startNewThread(User $user): AssistantThread
    {
        $open = $this->entityManager
            ->createQuery(<<<'DQL'
                SELECT t FROM App\AI\Entity\AssistantThread t
                WHERE t.userId = :userId AND t.status = :status
                DQL)
            ->setParameter('userId', $user->getId())
            ->setParameter('status', AssistantThread::OPEN)
            ->getResult();

        foreach ($open as $thread) {
            if (!$thread instanceof AssistantThread) {
                continue;
            }

            $thread->setStatus(AssistantThread::CLOSED);
            $thread->touch();
        }

        $created = $this->createOpen($user);
        $this->entityManager->flush();

        return $created;
    }

    /**
     * @return list<array{role: string, content: string, createdAt: string, actions: list<array<string, mixed>>|null}>
     */
    public function serializeRecentMessages(AssistantThread $thread): array
    {
        $all = $thread->getMessages()->toArray();
        $slice = array_slice($all, -self::HISTORY_LIMIT);
        $out = [];
        foreach ($slice as $message) {
            $out[] = [
                'role' => $message->getRole(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
                'actions' => $message->getActions(),
            ];
        }

        return $out;
    }

    /**
     * @param AssistantMessageInput[] $incoming
     * @param list<AssistantProposedAction> $proposed
     */
    public function persistTurn(User $user, array $incoming, string $assistantText, array $proposed = []): void
    {
        try {
            $thread = $this->currentOpenThread($user);
            $lastUser = $this->lastUserContent($incoming);
            if ($lastUser !== '') {
                $this->appendIfNew($thread, 'user', $lastUser, null);
            }
            if (trim($assistantText) !== '') {
                $actions = [];
                foreach ($proposed as $action) {
                    $actions[] = $action->toCard();
                }
                $this->appendIfNew($thread, 'assistant', $assistantText, $actions === [] ? null : $actions);
            }
            $this->entityManager->flush();
        } catch (Throwable $throwable) {
            $this->logger->warning('Assistant thread persist failed', [
                'error' => $throwable->getMessage(),
                'user' => $user->getUserIdentifier(),
            ]);
        }
    }

    private function findLatestOpen(User $user): ?AssistantThread
    {
        $found = $this->entityManager
            ->createQuery(<<<'DQL'
                SELECT t FROM App\AI\Entity\AssistantThread t
                WHERE t.userId = :userId AND t.status = :status
                ORDER BY t.updatedAt DESC
                DQL)
            ->setParameter('userId', $user->getId())
            ->setParameter('status', AssistantThread::OPEN)
            ->setMaxResults(1)
            ->getOneOrNullResult();

        return $found instanceof AssistantThread ? $found : null;
    }

    private function createOpen(User $user): AssistantThread
    {
        $thread = new AssistantThread();
        $thread->setUserId((int) $user->getId());
        $this->entityManager->persist($thread);

        return $thread;
    }

    /**
     * @param AssistantMessageInput[] $incoming
     */
    private function lastUserContent(array $incoming): string
    {
        for ($i = count($incoming) - 1; $i >= 0; --$i) {
            if ($incoming[$i]->role === 'user') {
                return trim($incoming[$i]->content);
            }
        }

        return '';
    }

    /**
     * @param list<array<string, mixed>>|null $actions
     */
    private function appendIfNew(AssistantThread $thread, string $role, string $content, ?array $actions): void
    {
        $last = $thread->getMessages()->last();
        if ($last instanceof AssistantThreadMessage && $last->getRole() === $role && $last->getContent() === $content) {
            return;
        }

        $message = new AssistantThreadMessage();
        $message->setRole($role);
        $message->setContent($content);
        $message->setActions($actions);
        $thread->addMessage($message);
        $this->entityManager->persist($message);
    }
}
