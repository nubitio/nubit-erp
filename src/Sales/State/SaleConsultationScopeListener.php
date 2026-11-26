<?php

declare(strict_types=1);

namespace App\Sales\State;

use App\Identity\Auth\BranchReportScope;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class SaleConsultationScopeListener
{
    public function __construct(
        private BranchReportScope $scope,
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
    public function apply(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (
            !$event->isMainRequest()
            || $request->attributes->get('_api_operation_name') !== 'sale_branch_consultation'
        ) {
            return;
        }

        if (!$this->security->isGranted('perm:sales.view')) {
            throw new AccessDeniedHttpException();
        }

        $branchId = $this->scope->requireBranchId($request->query->getString('branchId') ?: null);
        $this->entityManager->getFilters()->enable('branch_filter')->setParameterList('branchIds', [$branchId]);
    }
}
