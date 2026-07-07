<?php

declare(strict_types=1);

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\ApiResource\RolePermissionResource;
use App\Identity\Security\PermissionCatalog;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use Nubit\Platform\Http\ApiResponse;
use Override;

/**
 * Replaces the granted permission set of a role. Unknown permission keys are
 * silently dropped so stale clients can never persist grants outside the
 * catalog.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
readonly class RolePermissionProcessor implements ProcessorInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @inheritdoc
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
        if (!$data instanceof RolePermissionResource) {
            throw new InvalidArgumentException('Expected role permission resource.');
        }

        $roleId = $data->roleId;
        $permissions = array_values(array_unique(array_filter($data->permissions, PermissionCatalog::isValid(...))));

        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement('DELETE FROM role_permission WHERE role_id = :roleId', [
                'roleId' => $roleId,
            ]);

            foreach ($permissions as $permission) {
                $this->connection->executeStatement('INSERT INTO role_permission (role_id, permission) VALUES (:roleId, :permission)', [
                    'roleId' => $roleId,
                    'permission' => $permission,
                ]);
            }

            $this->connection->commit();

            return new ApiResponse(success: true, message: 'Permisos asignados correctamente');
        } catch (Exception) {
            $this->connection->rollBack();

            return new ApiResponse(success: false, message: 'Error al asignar permisos');
        }
    }
}
