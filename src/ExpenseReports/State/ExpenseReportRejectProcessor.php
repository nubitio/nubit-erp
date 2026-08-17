<?php

declare(strict_types=1);

namespace App\ExpenseReports\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ExpenseReports\Dto\ExpenseReportReviewDto;
use App\ExpenseReports\Entity\ExpenseReport;
use App\ExpenseReports\Service\ExpenseReportService;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<ExpenseReportReviewDto, ExpenseReport>
 */
readonly class ExpenseReportRejectProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ExpenseReportService $expenseReportService,
    ) {}

    /**
     * @inheritdoc
     *
     * @param ExpenseReportReviewDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ExpenseReport {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $expenseReport = $this->entityManager->find(ExpenseReport::class, $data->id);
        if (!$expenseReport instanceof ExpenseReport) {
            throw new NotFoundHttpException('Gasto no encontrado.');
        }

        $userRef = $this->entityManager->getReference(User::class, $user->userId);
        if (!$userRef instanceof User) {
            throw new NotFoundHttpException('Usuario no encontrado.');
        }

        $this->expenseReportService->transitionTo($expenseReport, ExpenseReport::REJECTED);
        $expenseReport->setReviewedBy($userRef);
        $expenseReport->setReviewedAt(new DateTimeImmutable());
        $expenseReport->setReviewNote($data->note);

        $this->entityManager->flush();

        return $expenseReport;
    }
}
