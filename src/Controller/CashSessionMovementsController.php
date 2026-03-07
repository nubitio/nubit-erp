<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Application\Session\Security;
use App\Dto\CashRegister\CashSessionMovementDto;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CRUD REST controller for cash session movements (v2 table: cash_movements).
 *
 * All operations work against the `cash_movements` DBAL table.
 * No ORM entities are used for cash_movements — DBAL only.
 *
 * Routes:
 *   GET    /api/cash-sessions/{cashSessionId}/movements
 *   POST   /api/cash-sessions/{cashSessionId}/movements
 *   PUT    /api/cash-sessions/{cashSessionId}/movements/{movId}
 *   DELETE /api/cash-sessions/{cashSessionId}/movements/{movId}
 */
#[Route('/api/cash-sessions/{cashSessionId}/movements', name: 'cash_session_movements_')]
class CashSessionMovementsController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Security $security,
    ) {
    }

    /**
     * AC-008-02 — List movements for a session (excludes SALE_IN and OPENING).
     *
     * @throws DBALException
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(int $cashSessionId): JsonResponse
    {
        if (!$this->security->isAuthenticated()) {
            return $this->json(['message' => 'No autenticado'], Response::HTTP_UNAUTHORIZED);
        }

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                cm.id,
                cm.movement_type,
                cm.amount,
                cm.description,
                cm.created_at,
                cm.category_id,
                cat.name AS category_name
            FROM cash_movements cm
            LEFT JOIN cash_movement_category cat ON cat.id = cm.category_id
            WHERE cm.cash_session_id = :sessionId
              AND cm.movement_type NOT IN ('SALE_IN', 'OPENING')
            ORDER BY cm.created_at ASC, cm.id ASC
            SQL,
            ['sessionId' => $cashSessionId],
        );

        $dtos = array_map(
            static fn (array $row): array => self::rowToResponseArray($row),
            $rows,
        );

        return $this->json($dtos);
    }

    /**
     * AC-008-01 — Create a new CASH_IN or CASH_OUT movement.
     *
     * @throws DBALException|MathException
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(int $cashSessionId, Request $request): JsonResponse
    {
        $user = $this->security->connectedUser();
        if ($user === null) {
            return $this->json(['message' => 'No autenticado'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->decodeJson($request);
        if ($payload === null) {
            return $this->json(['message' => 'JSON inválido'], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        $type        = $payload['type'] ?? null;
        $amountRaw   = $payload['amount'] ?? null;
        $categoryId  = isset($payload['categoryId']) ? (int) $payload['categoryId'] : null;
        $description = isset($payload['description']) ? (string) $payload['description'] : null;

        if (!in_array($type, ['income', 'expense'], true)) {
            return $this->json(
                ['message' => 'El campo type debe ser "income" o "expense"'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if ($amountRaw === null || $amountRaw === '') {
            return $this->json(['message' => 'El campo amount es requerido'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $amount = BigDecimal::of((string) $amountRaw)
                ->toScale(2, RoundingMode::HALF_UP)
                ->__toString();
        } catch (MathException) {
            return $this->json(['message' => 'El valor de amount no es válido'], Response::HTTP_BAD_REQUEST);
        }

        $movementType = $type === 'income' ? 'CASH_IN' : 'CASH_OUT';
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->insert('cash_movements', [
            'cash_session_id' => $cashSessionId,
            'movement_type'   => $movementType,
            'category_id'     => $categoryId,
            'amount'          => $amount,
            'description'     => $description,
            'created_by'      => $user->userId,
            'created_at'      => $now,
        ]);

        $newId = (int) $this->connection->lastInsertId();

        $row = $this->fetchMovementRow($newId);
        if ($row === null) {
            return $this->json(['message' => 'Error al recuperar el movimiento'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(self::rowToResponseArray($row), Response::HTTP_CREATED);
    }

    /**
     * AC-008-03 — Update amount, description and category_id of a movement.
     *
     * Only allows editing movements belonging to the specified session.
     *
     * @throws DBALException|MathException
     */
    #[Route('/{movId}', name: 'update', methods: ['PUT'])]
    public function update(int $cashSessionId, int $movId, Request $request): JsonResponse
    {
        if (!$this->security->isAuthenticated()) {
            return $this->json(['message' => 'No autenticado'], Response::HTTP_UNAUTHORIZED);
        }

        $row = $this->fetchMovementRow($movId);
        if ($row === null) {
            return $this->json(['message' => 'Movimiento no encontrado'], Response::HTTP_NOT_FOUND);
        }

        if ((int) $row['cash_session_id'] !== $cashSessionId) {
            return $this->json(
                ['message' => 'El movimiento no pertenece a esta sesión'],
                Response::HTTP_FORBIDDEN,
            );
        }

        $payload = $this->decodeJson($request);
        if ($payload === null) {
            return $this->json(['message' => 'JSON inválido'], Response::HTTP_BAD_REQUEST);
        }

        $updateData = [];

        if (isset($payload['amount'])) {
            try {
                $updateData['amount'] = BigDecimal::of((string) $payload['amount'])
                    ->toScale(2, RoundingMode::HALF_UP)
                    ->__toString();
            } catch (MathException) {
                return $this->json(['message' => 'El valor de amount no es válido'], Response::HTTP_BAD_REQUEST);
            }
        }

        if (array_key_exists('description', $payload)) {
            $updateData['description'] = $payload['description'] !== null ? (string) $payload['description'] : null;
        }

        if (array_key_exists('categoryId', $payload)) {
            $updateData['category_id'] = $payload['categoryId'] !== null ? (int) $payload['categoryId'] : null;
        }

        if ($updateData !== []) {
            $this->connection->update(
                'cash_movements',
                $updateData,
                ['id' => $movId],
            );
        }

        $updated = $this->fetchMovementRow($movId);
        if ($updated === null) {
            return $this->json(
                ['message' => 'Error al recuperar el movimiento actualizado'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $this->json(self::rowToResponseArray($updated), Response::HTTP_OK);
    }

    /**
     * AC-008-04 — Delete a CASH_IN or CASH_OUT movement.
     *
     * Only allows deleting movements belonging to the specified session.
     *
     * @throws DBALException
     */
    #[Route('/{movId}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $cashSessionId, int $movId): JsonResponse
    {
        if (!$this->security->isAuthenticated()) {
            return $this->json(['message' => 'No autenticado'], Response::HTTP_UNAUTHORIZED);
        }

        $row = $this->fetchMovementRow($movId);
        if ($row === null) {
            return $this->json(['message' => 'Movimiento no encontrado'], Response::HTTP_NOT_FOUND);
        }

        if ((int) $row['cash_session_id'] !== $cashSessionId) {
            return $this->json(
                ['message' => 'El movimiento no pertenece a esta sesión'],
                Response::HTTP_FORBIDDEN,
            );
        }

        if (!in_array($row['movement_type'], ['CASH_IN', 'CASH_OUT'], true)) {
            return $this->json(
                ['message' => 'Solo se pueden eliminar movimientos de tipo CASH_IN o CASH_OUT'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->connection->delete('cash_movements', ['id' => $movId]);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Decode JSON body from request; returns null on failure.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJson(Request $request): ?array
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            $raw = json_decode((string) $request->getContent(), true);
            $data = is_array($raw) ? $raw : null;
        }

        return $data;
    }

    /**
     * Fetch a single movement row with category join.
     *
     * @return array<string, mixed>|null
     *
     * @throws DBALException
     */
    private function fetchMovementRow(int $movId): ?array
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
            SELECT
                cm.id,
                cm.cash_session_id,
                cm.movement_type,
                cm.amount,
                cm.description,
                cm.created_at,
                cm.category_id,
                cat.name AS category_name
            FROM cash_movements cm
            LEFT JOIN cash_movement_category cat ON cat.id = cm.category_id
            WHERE cm.id = :id
            SQL,
            ['id' => $movId],
        );

        return $row !== false ? $row : null;
    }

    /**
     * Convert a DBAL row to the frontend-compatible response array.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function rowToResponseArray(array $row): array
    {
        $dto = CashSessionMovementDto::fromRow($row);

        $response = [
            'id'          => $dto->id,
            'type'        => $dto->type,
            'amount'      => $dto->amount,
            'description' => $dto->description,
            'createdAt'   => $dto->createdAt,
        ];

        if ($dto->category !== null) {
            $response['category'] = $dto->category;
        } else {
            $response['category'] = null;
        }

        return $response;
    }
}
