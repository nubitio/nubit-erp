<?php

declare(strict_types=1);

namespace App\Inventory\Doctrine;

use App\Identity\Auth\BranchReportScope;
use App\Inventory\Entity\StockItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class StockReportingScopeListener
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
            || !$request->isMethod('GET')
            || $request->attributes->get('_api_resource_class') !== StockItem::class
        ) {
            return;
        }

        $branchId = $request->query->getString('branchId');
        if ($branchId === '') {
            return;
        }

        if (!$this->security->isGranted('perm:inventory.view')) {
            throw new AccessDeniedHttpException();
        }

        $branchId = $this->scope->requireBranchId($branchId);
        $this->entityManager->getFilters()->enable('branch_filter')->setParameterList('branchIds', [$branchId]);
    }
}
