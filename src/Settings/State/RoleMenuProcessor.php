<?php

declare(strict_types=1);

namespace App\Settings\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Settings\ApiResource\RoleMenuResource;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Nubit\Platform\Http\ApiResponse;
use Override;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
readonly class RoleMenuProcessor implements ProcessorInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @inheritdoc
     *
     * @param RoleMenuResource $data
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
        $roleId = $data->roleId;

        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement('DELETE FROM menu_rol WHERE role_id = :roleId', ['roleId' => $roleId]);

            foreach ($data->permissions as $menuId) {
                $this->connection->executeStatement('INSERT INTO menu_rol (menu_id, role_id) VALUES (:menuId, :roleId)', [
                    'menuId' => $menuId,
                    'roleId' => $roleId,
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
