<?php

declare(strict_types=1);

namespace App\Accounting\Repository;

use App\Accounting\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function findPostableByCode(string $code): ?Account
    {
        $account = $this->findOneBy(['code' => $code, 'active' => true]);
        if ($account instanceof Account && !$account->isHeader()) {
            return $account;
        }

        return null;
    }
}
