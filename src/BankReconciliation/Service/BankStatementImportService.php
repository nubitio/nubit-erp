<?php

declare(strict_types=1);

namespace App\BankReconciliation\Service;

use App\BankReconciliation\Entity\BankAccount;
use App\BankReconciliation\Entity\BankStatementImport;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

readonly class BankStatementImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Creates the job record synchronously so the client gets an id/topic
     * to subscribe to before the async parsing message is dispatched.
     */
    public function startImport(
        BankAccount $bankAccount,
        string $bankProfile,
        string $fileName,
        User $createdBy,
    ): BankStatementImport {
        $import = new BankStatementImport()
            ->setBankAccount($bankAccount)
            ->setBankProfile($bankProfile)
            ->setFileName($fileName)
            ->setCreatedBy($createdBy)
            ->setStatus(BankStatementImport::PROCESSING);

        $this->entityManager->persist($import);
        $this->entityManager->flush();

        return $import;
    }
}
