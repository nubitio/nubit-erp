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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<CashRegisterCloseDto, ApiResponse>
 */
readonly class CashRegisterCloseProcessor implements ProcessorInterface
{
    public function __construct(
        private Connection $connection,
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
        $authUser = $this->security->connectedUser();
        if ($authUser === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $cashSessionId = (int) $uriVariables['id'];

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

        $expectedStr = $expectedTotal->toScale(4, RoundingMode::HALF_UP)->__toString();
        $countedStr = $countedTotal->toScale(4, RoundingMode::HALF_UP)->__toString();
        $differenceStr = $differenceTotal->toScale(4, RoundingMode::HALF_UP)->__toString();

        $this->connection->beginTransaction();
        try {
            // Verify the session exists and is open
            $session = $this->connection->fetchAssociative(
                'SELECT id, opening_amount FROM cash_sessions WHERE id = ? AND status = ?',
                [$cashSessionId, 'OPEN']
            );
            if ($session === false) {
                $this->connection->rollBack();
                throw new NotFoundHttpException('Turno de caja no encontrado o ya cerrado.');
            }

            // Calculate closing_balance dynamically:
            // opening_amount + SUM(CASH_IN) - SUM(CASH_OUT) + SUM(SALE_IN cash)
            $openingAmount = BigDecimal::of((string) $session['opening_amount']);

            $cashIn = BigDecimal::of((string) ($this->connection->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
                 WHERE cash_session_id = ? AND movement_type = 'CASH_IN'",
                [$cashSessionId]
            ) ?: '0'));

            $cashOut = BigDecimal::of((string) ($this->connection->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
                 WHERE cash_session_id = ? AND movement_type = 'CASH_OUT'",
                [$cashSessionId]
            ) ?: '0'));

            $saleIn = BigDecimal::of((string) ($this->connection->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
                 WHERE cash_session_id = ? AND movement_type = 'SALE_IN'",
                [$cashSessionId]
            ) ?: '0'));

            $closingBalance = $openingAmount
                ->plus($cashIn)
                ->minus($cashOut)
                ->plus($saleIn)
                ->toScale(4, RoundingMode::HALF_UP)
                ->__toString();

            $note = $notes !== [] ? implode('; ', $notes) : null;

            $this->connection->executeStatement(
                "UPDATE cash_sessions
                 SET status = 'CLOSED',
                     closed_at = NOW(),
                     closed_by = ?,
                     closing_amount = ?,
                     expected_amount = ?,
                     counted_amount = ?,
                     difference_amount = ?,
                     note = ?
                 WHERE id = ?",
                [
                    $authUser->userId,
                    $closingBalance,
                    $expectedStr,
                    $countedStr,
                    $differenceStr,
                    $note,
                    $cashSessionId,
                ]
            );

            $this->connection->commit();
        } catch (NotFoundHttpException $nfe) {
            throw $nfe;
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            return new ApiResponse(
                success: false,
                message: 'Error al cerrar la caja',
                data: $e->getMessage(),
            );
        }

        return new ApiResponse(
            success: true,
            message: 'Caja cerrada correctamente',
        );
    }
}
