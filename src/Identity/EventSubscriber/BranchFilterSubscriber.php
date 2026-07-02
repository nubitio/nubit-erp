<?php

declare(strict_types=1);

namespace App\Identity\EventSubscriber;

use App\Identity\Auth\BranchContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Activates the BranchFilter for each request based on the authenticated user's branch.
 */
class BranchFilterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BranchContextService $branchContextService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $branchId = $this->branchContextService->getCurrentBranchId();
        if ($branchId === null) {
            return;
        }

        $filters = $this->entityManager->getFilters();

        if (!$filters->isEnabled('branch_filter')) {
            $filters->enable('branch_filter');
        }

        $filters->getFilter('branch_filter')->setParameter('branchId', $branchId);
    }
}
