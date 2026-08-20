<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Enum\CashMovementType;
use App\Identity\Security\Security;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Custom state processor that writes CashMovement records directly to the
 * v2 cash_movements table via DBAL, bypassing ORM and the legacy cash_movement table.
 *
 * Handles append-only POST operations on /api/cash-movements.
 *
 * @implements ProcessorInterface<CashMovement, CashMovement|null>
 */
readonly class CashMovementWriteProcessor implements ProcessorInterface
{
    public function __construct(
        private Connection $connection,
        private Security $security,
    ) {}

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ?CashMovement {
        if (!$operation instanceof Post) {
            throw new MethodNotAllowedHttpException(
                ['POST'],
                'Los movimientos de caja son append-only. Registra una corrección en lugar de modificar o eliminar.',
            );
        }

        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $cashSession = $data->getCashSession();
        if ($cashSession === null) {
            throw new UnprocessableEntityHttpException('El campo cashSession es obligatorio.');
        }

        $categoryId = $data->getCategory()?->getId();
        $amount = $data->getAmount() ?? '0.00';
        if ($categoryId === null) {
            throw new UnprocessableEntityHttpException('El campo category es obligatorio.');
        }
        if (!is_numeric($amount) || (float) $amount <= 0) {
            throw new UnprocessableEntityHttpException('El monto debe ser un número mayor que cero.');
        }
        $description = $data->getDescription();

        $this->connection->beginTransaction();
        try {
            $sessionRow = $this->connection->fetchAssociative(
                'SELECT status, opened_by FROM cash_sessions WHERE id = :id FOR UPDATE',
                ['id' => $cashSession->getId()],
            );
            if ($sessionRow === false || $sessionRow['status'] !== 'OPEN') {
                throw new UnprocessableEntityHttpException('La sesión de caja no existe o está cerrada.');
            }
            if ((int) $sessionRow['opened_by'] !== $user->userId && !$this->security->hasPermission('cash.movement_any')) {
                throw new AccessDeniedHttpException('No puedes registrar movimientos en la caja de otro usuario.');
            }

            $categoryRow = $this->connection->fetchAssociative(
                'SELECT type, active FROM cash_movement_category WHERE id = :id',
                ['id' => $categoryId],
            );
            if ($categoryRow === false || !(bool) $categoryRow['active']) {
                throw new UnprocessableEntityHttpException('La categoría de movimiento no existe o está inactiva.');
            }
            $movementType = $categoryRow['type'] === CashMovementType::EXPENSE->value ? 'CASH_OUT' : 'CASH_IN';

            $this->connection->executeStatement('INSERT INTO cash_movements
                (cash_session_id, movement_type, category_id, amount, description, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())', [
                $cashSession->getId(),
                $movementType,
                $categoryId,
                $amount,
                $description,
                $user->userId,
            ]);
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        $newId = (int) $this->connection->lastInsertId();

        // Set the generated PK on the entity so API Platform can build the IRI.
        $idProp = new \ReflectionProperty(CashMovement::class, 'id');
        $idProp->setValue($data, $newId);

        // Set createdAt (ORM PrePersist won't fire since we bypass ORM).
        $data->setCreatedAt(new DateTimeImmutable());

        return $data;
    }
}
