<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Delete;
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
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Custom state processor that writes CashMovement records directly to the
 * v2 cash_movements table via DBAL, bypassing ORM and the legacy cash_movement table.
 *
 * Handles POST, PATCH, and DELETE operations on /api/cash-movements.
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
        // DELETE — remove from v2 table and return null (204 No Content)
        if ($operation instanceof Delete) {
            $id = (int) ($uriVariables['id'] ?? 0);
            if ($id > 0) {
                $this->connection->executeStatement('DELETE FROM cash_movements WHERE id = ?', [$id]);
            }

            return null;
        }

        // POST (create) or PATCH (update)
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $cashSession = $data->getCashSession();
        if ($cashSession === null) {
            throw new UnprocessableEntityHttpException('El campo cashSession es obligatorio.');
        }

        $movementType = match ($data->getType()) {
            CashMovementType::EXPENSE => 'CASH_OUT',
            default => 'CASH_IN',
        };

        $categoryId = $data->getCategory()?->getId();
        $amount = $data->getAmount() ?? '0.00';
        $description = $data->getDescription();

        if ($operation instanceof Post) {
            // INSERT into v2 table
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

            $newId = (int) $this->connection->lastInsertId();

            // Set the generated PK on the entity so API Platform can build the IRI
            $idProp = new \ReflectionProperty(CashMovement::class, 'id');
            $idProp->setValue($data, $newId);

            // Set createdAt (ORM PrePersist won't fire since we bypass ORM)
            $data->setCreatedAt(new DateTimeImmutable());
        } else {
            // PATCH — UPDATE existing row
            $id = (int) ($uriVariables['id'] ?? 0);
            $this->connection->executeStatement('UPDATE cash_movements
                 SET movement_type = ?, category_id = ?, amount = ?, description = ?
                 WHERE id = ?', [
                $movementType,
                $categoryId,
                $amount,
                $description,
                $id,
            ]);
        }

        return $data;
    }
}
