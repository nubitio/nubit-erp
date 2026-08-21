<?php

declare(strict_types=1);

namespace App\AI\Service;

use App\AI\Entity\AssistantProposedAction;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ProposedActionFactory
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ProposedActionCollector $collector,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function propose(string $kind, string $summary, array $payload): AssistantProposedAction
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            throw new AccessDeniedException('Se requiere un usuario autenticado.');
        }

        $action = new AssistantProposedAction();
        $action->setUserId($user->getId());
        $action->setKind($kind);
        $action->setSummary($summary);
        $action->setPayload($payload);

        $this->entityManager->persist($action);
        $this->entityManager->flush();
        $this->collector->add($action);

        return $action;
    }
}
