<?php

declare(strict_types=1);

namespace App\State\Purchase;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Security\Auth\BranchContextService;
use App\Entity\Branch;
use App\Entity\Purchase;
use App\Entity\User;
use App\Repository\BranchRepository;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
readonly class PurchaseProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private BranchContextService $branchContextService,
        private BranchRepository $branchRepository,
    ) {
    }

    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Assign UUID v7 on new entity (when $data->getId() is null)
        if ($data instanceof Purchase && $data->getId() === null) {
            $data->setId(Uuid::v7()->toRfc4122());
        }

        // Auto-populate fields that the form does not send
        if ($data instanceof Purchase) {
            // 1. Set buyerUser from the authenticated user
            if ($data->getBuyerUser() === null) {
                $user = $this->security->getUser();
                if ($user instanceof User) {
                    $data->setBuyerUser($user);
                }
            }

            // 2. Default issuedAt to now if not provided
            if ($data->getIssuedAt() === null) {
                $data->setIssuedAt(new \DateTime());
            }

            // 3. Set branch from the current JWT session context (branch is not on User entity)
            if ($data->getBranch() === null) {
                $branchId = $this->branchContextService->getCurrentBranchId();
                if ($branchId !== null) {
                    $branch = $this->branchRepository->find($branchId);
                    if ($branch instanceof Branch) {
                        $data->setBranch($branch);
                    }
                }
            }

            // 4. Ensure each item's owning-side back-reference is set before Doctrine persist.
            //    API Platform may denormalise items directly into the collection without calling
            //    addItem(), so the ManyToOne foreign key would be left null.
            foreach ($data->getItems() as $item) {
                if ($item->getPurchase() !== $data) {
                    $item->setPurchase($data);
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
