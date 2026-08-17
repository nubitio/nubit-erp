<?php

declare(strict_types=1);

namespace App\ExpenseReports\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ExpenseReports\Dto\ExpenseReportReimburseDto;
use App\ExpenseReports\Entity\ExpenseReport;
use App\ExpenseReports\Service\ExpenseReportService;
use App\Identity\Security\Security;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<ExpenseReportReimburseDto, ExpenseReport>
 */
readonly class ExpenseReportReimburseProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ExpenseReportService $expenseReportService,
    ) {}

    /**
     * @inheritdoc
     *
     * @param ExpenseReportReimburseDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ExpenseReport {
        if ($this->security->connectedUser() === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $expenseReport = $this->entityManager->find(ExpenseReport::class, $data->id);
        if (!$expenseReport instanceof ExpenseReport) {
            throw new NotFoundHttpException('Gasto no encontrado.');
        }

        $this->expenseReportService->transitionTo($expenseReport, ExpenseReport::REIMBURSED);
        $expenseReport->setReimbursedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $expenseReport;
    }
}
