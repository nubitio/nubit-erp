<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use Override;
use DateTimeImmutable;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Http\ApiResponse;
use App\Dto\CashRegister\CashRegisterCloseDto;
use App\Entity\CashShift;
use App\Entity\User;
use App\Enum\CashShiftStatus;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class CashRegisterCloseProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param CashRegisterCloseDto $data
     *
     * @throws Exception
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        $closureItems = $data->closureItems ?? [];
        $expectedTotal = 0.0;
        $countedTotal = 0.0;
        $differenceTotal = 0.0;
        $notes = [];

        if (!empty($closureItems)) {
            foreach ($closureItems as $item) {
                $expectedTotal += (float) ($item['expectedAmount'] ?? 0);
                $countedTotal += (float) ($item['countedAmount'] ?? 0);
                $differenceTotal += (float) ($item['difference'] ?? 0);
                if (!empty($item['note'])) {
                    $notes[] = trim((string) $item['note']);
                }
            }
        } else {
            $expectedTotal = (float) ($data->expectedAmount ?? 0);
            $countedTotal = (float) ($data->countedAmount ?? 0);
            $differenceTotal = (float) (($data->difference ?? ($countedTotal - $expectedTotal)));
            if (!empty($data->note)) {
                $notes[] = trim((string) $data->note);
            }
        }

        $this->entityManager->getConnection()->beginTransaction();
        try {
            $cashShift = $this->entityManager->find(
                CashShift::class,
                $uriVariables['id']
            );
            $cashShift->setClosingBalance($countedTotal);
            $cashShift->setExpectedAmount($expectedTotal);
            $cashShift->setCountedAmount($countedTotal);
            $cashShift->setDifference($differenceTotal);
            $cashShift->setNote($notes !== [] ? implode('; ', $notes) : null);
            $cashShift->setStatus(CashShiftStatus::CLOSED);
            $cashShift->setClosedBy(
                $this->entityManager->getReference(
                    User::class,
                    $this->security->connectedUser()->userId
                )
            );
            $cashShift->setClosedAt(new DateTimeImmutable());
            $this->entityManager->persist($cashShift);

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (ORMException $ormException) {
            $this->entityManager->getConnection()->rollBack();

            return new ApiResponse(
                success: false,
                message: 'Error al cerrar la caja',
                data: $ormException->getMessage(),
            );
        }

        return new ApiResponse(
            success: true,
            message: 'Caja cerrada correctamente',
        );
    }
}
