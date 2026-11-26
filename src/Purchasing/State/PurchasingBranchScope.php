<?php

declare(strict_types=1);

namespace App\Purchasing\State;

use App\Identity\Auth\OperatingBranchResolver;
use App\Identity\Entity\User;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class PurchasingBranchScope
{
    private const string BRANCH_ATTRIBUTE = '_authorized_purchasing_branch';

    public function __construct(
        private RequestStack $requests,
        private Security $security,
        private OperatingBranchResolver $branches,
        private EntityManagerInterface $entityManager,
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
    public function apply(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!in_array(
            $request->attributes->get('_api_resource_class'),
            [Purchase::class, PurchaseOrder::class],
            true,
        )) {
            return;
        }

        $branchId = $request->query->getString('branchId');
        if ($branchId === '') {
            return;
        }

        $this->authorize($branchId);

        $request->attributes->set(self::BRANCH_ATTRIBUTE, $branchId);
        $this->entityManager->getFilters()->enable('branch_filter')->setParameterList('branchIds', [$branchId]);
    }

    }

    public function authorize(string $branchId): void
    {
        $user = $this->security->getUser();
        if (
            !$user instanceof User
            || !$this->security->isGranted('perm:purchases.cross_branch')
            || $this->branches->resolve($user, $branchId) === null
        ) {
            throw new AccessDeniedHttpException('No tienes permiso para gestionar compras de esta sucursal.');
        }
    }

    public function authorizedBranchId(): ?string
    {
        return $this->requests->getMainRequest()?->attributes->getString(self::BRANCH_ATTRIBUTE) ?: null;
    }
}
