<?php

namespace App\State\RoleMenu;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\RoleMenuResource;
use App\Core\Domain\Common\ApiResponse;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class RoleMenuProcessor implements ProcessorInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param RoleMenuResource $data
     *
     * @throws Exception
     */
    #[\Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        $roleId = $data->roleId;

        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement(
                'DELETE FROM menu_rol WHERE rol_id = :roleId',
                ['roleId' => $roleId]
            );

            $insertStmt = $this->connection->prepare(
                'INSERT INTO menu_rol (menu_id, rol_id, estado) VALUES (:menuId, :roleId, :checked)'
            );

            foreach ($data->permissions as $menuId) {
                $insertStmt->executeStatement([
                    'menuId' => $menuId,
                    'roleId' => $roleId,
                    'checked' => true,
                ]);
            }

            $this->connection->commit();

            return new ApiResponse(
                success: true,
                message: 'Permisos asignados correctamente'
            );
        } catch (Exception) {
            $this->connection->rollBack();

            return new ApiResponse(
                success: false,
                message: 'Error al asignar permisos'
            );
        }
    }
}
