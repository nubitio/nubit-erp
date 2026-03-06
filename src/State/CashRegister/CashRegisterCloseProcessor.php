<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use Override;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<CashRegisterCloseDto, ApiResponse>
 */
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
        $notes = [];

        if (!empty($closureItems)) {
            $expectedTotal = BigDecimal::zero();
            $countedTotal = BigDecimal::zero();
            foreach ($closureItems as $item) {
                $expectedTotal = $expectedTotal->plus(BigDecimal::of($item['expectedAmount'] ?? '0'));
                $countedTotal = $countedTotal->plus(BigDecimal::of($item['countedAmount'] ?? '0'));
                if (!empty($item['note'])) {
                    $notes[] = trim((string) $item['note']);
                }
            }

            $differenceTotal = $countedTotal->minus($expectedTotal);
        } else {
            $expectedTotal = BigDecimal::of($data->expectedAmount ?? '0');
            $countedTotal = BigDecimal::of($data->countedAmount ?? '0');
            $differenceTotal = isset($data->difference)
                ? BigDecimal::of($data->difference)
                : $countedTotal->minus($expectedTotal);
            if (!empty($data->note)) {
                $notes[] = trim((string) $data->note);
            }
        }

        $expectedStr = $expectedTotal->toScale(2, RoundingMode::HALF_UP)->__toString();
        $countedStr = $countedTotal->toScale(2, RoundingMode::HALF_UP)->__toString();
        $differenceStr = $differenceTotal->toScale(2, RoundingMode::HALF_UP)->__toString();

        $this->entityManager->getConnection()->beginTransaction();
        try {
            $cashShift = $this->entityManager->find(
                CashShift::class,
                $uriVariables['id']
            );
            if (!$cashShift instanceof CashShift) {
                throw new NotFoundHttpException('Turno de caja no encontrado.');
            }

            $authUser = $this->security->connectedUser();
            if ($authUser === null) {
                throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
            }

            $cashShift->setClosingBalance($countedStr);
            $cashShift->setExpectedAmount($expectedStr);
            $cashShift->setCountedAmount($countedStr);
            $cashShift->setDifference($differenceStr);
            $cashShift->setNote($notes !== [] ? implode('; ', $notes) : null);
            $cashShift->setStatus(CashShiftStatus::CLOSED);

            $userRef = $this->entityManager->getReference(User::class, $authUser->userId);
            if ($userRef instanceof User) {
                $cashShift->setClosedBy($userRef);
            }

            $cashShift->setClosedAt(new DateTimeImmutable());
            $this->entityManager->persist($cashShift);

            $this->entityManager->flush();

            // Close V2 cash_session linked to this legacy shift
            $userId = $authUser->userId;
            $this->entityManager->getConnection()->executeStatement(
                "UPDATE cash_sessions
                 SET status = 'CLOSED', closed_at = NOW(), closed_by = ?,
                     expected_amount = ?, counted_amount = ?, difference_amount = ?
                 WHERE legacy_cash_shift_id = ?",
                [$userId, $expectedStr, $countedStr, $differenceStr, $uriVariables['id']]
            );

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
