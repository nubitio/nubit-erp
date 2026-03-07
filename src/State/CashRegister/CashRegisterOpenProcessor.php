<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use Override;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Security\Auth\BranchContextService;
use App\Dto\CashRegister\CashRegisterOpenDto;
use App\Dto\CashRegister\CashRegisterOpenResponseDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<CashRegisterOpenDto, CashRegisterOpenResponseDto>
 */
readonly class CashRegisterOpenProcessor implements ProcessorInterface
{
    public function __construct(
        private Connection $connection,
        private Security $security,
        private BranchContextService $branchContextService,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param CashRegisterOpenDto $data
     *
     * @throws MathException
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): CashRegisterOpenResponseDto {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $scaledAmount = BigDecimal::of($data->openingBalance)
            ->toScale(4, RoundingMode::HALF_UP)
            ->__toString();

        $branchId = $this->branchContextService->getCurrentBranchId();
        $openedAt = new DateTimeImmutable();

        $this->connection->beginTransaction();
        try {
            $this->connection->insert('cash_sessions', [
                'legacy_cash_shift_id' => null,
                'cash_register_id'     => $data->id,
                'branch_id'            => $branchId,
                'opened_by'            => $user->userId,
                'status'               => 'OPEN',
                'opening_amount'       => $scaledAmount,
                'opened_at'            => $openedAt->format('Y-m-d H:i:s'),
            ]);

            $cashSessionId = (int) $this->connection->lastInsertId();

            // Insert OPENING movement for audit trail
            $this->connection->insert('cash_movements', [
                'cash_session_id' => $cashSessionId,
                'movement_type'   => 'OPENING',
                'amount'          => $scaledAmount,
                'description'     => 'Saldo inicial',
                'created_by'      => $user->userId,
                'created_at'      => $openedAt->format('Y-m-d H:i:s'),
            ]);

            $this->connection->commit();
        } catch (UniqueConstraintViolationException) {
            $this->connection->rollBack();

            throw new ConflictHttpException('Esta caja ya tiene un turno abierto.');
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }

        return new CashRegisterOpenResponseDto(
            cashSessionId: $cashSessionId,
            cashRegisterId: $data->id,
            openingAmount: $scaledAmount,
            openedAt: $openedAt,
        );
    }
}
