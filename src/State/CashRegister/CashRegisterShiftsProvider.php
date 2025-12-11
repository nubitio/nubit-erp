<?php

namespace App\State\CashRegister;

use Override;
use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CashShift;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

readonly class CashRegisterShiftsProvider implements ProviderInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Paginator
    {
        $cashRegisterId = $uriVariables['id'] ?? null;

        $filters = $context['filters'] ?? [];
        $itemsPerPage = isset($filters['itemsPerPage']) ? (int)$filters['itemsPerPage'] : 20;
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;

        // DevExtreme may send skip/take instead of page/itemsPerPage.
        if (isset($filters['take'])) {
            $itemsPerPage = (int)$filters['take'];
        }

        if (isset($filters['skip']) && $itemsPerPage > 0) {
            $page = (int)floor(((int)$filters['skip']) / $itemsPerPage) + 1;
        }

        $offset = max(0, ($page - 1) * $itemsPerPage);

        $qb = $this->entityManager
            ->getRepository(CashShift::class)
            ->createQueryBuilder('cs')
            ->andWhere('cs.cashRegister = :cashRegister')
            ->setParameter('cashRegister', $cashRegisterId)
            ->orderBy('cs.openedAt', 'DESC')
            ->addOrderBy('cs.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($itemsPerPage);

        $doctrinePaginator = new DoctrinePaginator($qb);

        return new Paginator($doctrinePaginator);
    }
}
