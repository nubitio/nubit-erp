<?php

declare(strict_types=1);

namespace App\Identity\EventSubscriber;

use App\Identity\Auth\BranchContextServiceInterface;
use Nubit\AdminBundle\Auth\JWTAuthenticationToken;
use Symfony\Bundle\SecurityBundle\Security;
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
        private readonly BranchContextServiceInterface $branchContextService,
        private readonly Security $security,
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
        if ($branchId === null && !$this->security->getToken() instanceof JWTAuthenticationToken) {
            return;
        }

        $filters = $this->entityManager->getFilters();

        if (!$filters->isEnabled('branch_filter')) {
            $filters->enable('branch_filter');
        }

        $filters->getFilter('branch_filter')->setParameterList('branchIds', $branchId === null ? [] : [$branchId]);
    }
}
