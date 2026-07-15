<?php

declare(strict_types=1);

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\ApiResource\RolePermissionResource;
use App\Identity\Entity\Role;
use App\Identity\Entity\RolePermission;
use App\Identity\Security\PermissionCatalog;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @inheritdoc
     *
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

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $this->entityManager
                ->createQuery('DELETE FROM App\\Identity\\Entity\\RolePermission rp WHERE rp.role = :roleId')
                ->setParameter('roleId', $roleId)
                ->execute();

            $role = $this->entityManager->getReference(Role::class, $roleId);
            foreach ($permissions as $permission) {
                $this->entityManager->persist(
                    (new RolePermission())
                        ->setRole($role)
                        ->setPermission($permission),
                );
            }

            $this->entityManager->flush();
            $connection->commit();

            return new ApiResponse(success: true, message: 'Permisos asignados correctamente');
        } catch (\Throwable) {
            $connection->rollBack();

            return new ApiResponse(success: false, message: 'Error al asignar permisos');
        }
    }
}
