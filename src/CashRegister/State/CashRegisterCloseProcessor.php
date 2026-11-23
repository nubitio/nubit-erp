<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\CashRegister\Dto\CashRegisterCloseDto;
use App\CashRegister\Dto\CashRegisterCloseResponseDto;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\Service\ClosureCount;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Override;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<CashRegisterCloseDto, CashRegisterCloseResponseDto>
 */
readonly class CashRegisterCloseProcessor implements ProcessorInterface
{
    /** The denominations of the till, in cents: S/ 200, 100, 50, 20, 10, 5, 2, 1, 0.50, 0.20 and 0.10. */
    private const array DENOMINATIONS_IN_CENTS = [20_000, 10_000, 5_000, 2_000, 1_000, 500, 200, 100, 50, 20, 10];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {}

    /**
     * @inheritdoc
     *
     * @param CashRegisterCloseDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): CashRegisterCloseResponseDto {
        ['counted' => $countedTotal, 'notes' => $notes] = ClosureCount::of($data);

        try {
            $cashSession = $this->entityManager->find(CashSession::class, $uriVariables['id']);

            if (!$cashSession instanceof CashSession) {
                throw new NotFoundHttpException('Sesión de caja no encontrada.');
            }

            $authUser = $this->security->connectedUser();
            if ($authUser === null) {
                throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
            }
            if ($cashSession->getStatus() !== 'OPEN') {
                throw new ConflictHttpException('La sesión de caja ya está cerrada.');
            }
            if (
                $cashSession->getOpenedBy()->getId() !== $authUser->userId
                && !$this->security->hasPermission('cash.close_any')
            ) {
                throw new AccessDeniedHttpException('No puedes cerrar la sesión de caja de otro usuario.');
            }

            $expectedAmount = $this->entityManager->getConnection()->fetchOne(
                <<<'SQL'
                    SELECT COALESCE(SUM(CASE
                        WHEN movement_type IN ('SALE_IN', 'CASH_IN') THEN amount
                        WHEN movement_type IN ('REFUND_OUT', 'CASH_OUT') THEN -amount
                        ELSE 0
                    END), 0)
                    FROM cash_movements
                    WHERE cash_session_id = :sessionId
                    SQL,
                ['sessionId' => $cashSession->getId()],
            );
            // The session owns the opening amount. OPENING is its audit row,
            // which may be absent in older sessions and must not be counted twice.
            $expectedTotal = BigDecimal::of($cashSession->getOpeningAmount())->plus((string) $expectedAmount);
            $differenceTotal = $countedTotal->minus($expectedTotal);
            $expectedStr = $expectedTotal->toScale(2, RoundingMode::HALF_UP)->__toString();
            $countedStr = $countedTotal->toScale(2, RoundingMode::HALF_UP)->__toString();
            $differenceStr = $differenceTotal->toScale(2, RoundingMode::HALF_UP)->__toString();

            $userRef = $this->entityManager->getReference(User::class, $authUser->userId);

            $cashSession->setCountedAmount($countedStr);
            $cashSession->setExpectedAmount($expectedStr);
            $cashSession->setDifferenceAmount($differenceStr);
            $cashSession->setCloseNote($notes !== [] ? implode('; ', $notes) : null);
            $cashSession->setCountedDenominations(self::cleanDenominations($data->denominations));
            $cashSession->setOpeningFundKept($data->fundKept);
            if ($data->fundKept !== null) {
                $stays = $data->fundKept ? BigDecimal::of($cashSession->getOpeningAmount()) : BigDecimal::zero();
                $taken = $countedTotal->minus($stays);
                $cashSession->setCashWithdrawnAtClose(
                    ($taken->isNegative() ? BigDecimal::zero() : $taken)
                        ->toScale(2, RoundingMode::HALF_UP)
                        ->__toString(),
                );
            }
            $cashSession->setStatus('CLOSED');
            $cashSession->setClosedBy($userRef instanceof User ? $userRef : null);
            $cashSession->setClosedAt(new DateTimeImmutable());

            $this->entityManager->persist($cashSession);
            $this->entityManager->flush();
        } catch (ORMException $ormException) {
            return new CashRegisterCloseResponseDto(
                success: false,
                message: 'Error al cerrar la caja',
                data: $ormException->getMessage(),
            );
        }

        return new CashRegisterCloseResponseDto(success: true, message: 'Caja cerrada correctamente');
    }

    /**
     * Only whole, positive quantities of the denominations the till knows are kept: the count is a record, never
     * something that moves the totals.
     *
     * @param array<array-key, mixed>|null $denominations
     * @return array<int|string, int>|null
     */
    private static function cleanDenominations(?array $denominations): ?array
    {
        if ($denominations === null) {
            return null;
        }

        $clean = [];
        foreach ($denominations as $cents => $quantity) {
            if (
                in_array((int) $cents, self::DENOMINATIONS_IN_CENTS, strict: true)
                && is_numeric($quantity)
                && (int) $quantity > 0
            ) {
                $clean[(int) $cents] = (int) $quantity;
            }
        }

        return $clean === [] ? null : $clean;
    }
}
