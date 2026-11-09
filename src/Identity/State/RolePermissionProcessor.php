<?php

declare(strict_types=1);

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Activity\ActivityType;
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
        private ActivityRecorderInterface $activity,
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
        $before = array_map(
            strval(...),
            $connection->fetchFirstColumn('SELECT permission FROM role_permission WHERE role_id = ?', [$roleId]),
        );
        $connection->beginTransaction();
        try {
            $this->entityManager
                ->createQuery('DELETE FROM App\\Identity\\Entity\\RolePermission rp WHERE rp.role = :roleId')
                ->setParameter('roleId', $roleId)
                ->execute();

            $role = $this->entityManager->getReference(Role::class, $roleId);
            if (!$role instanceof Role) {
                throw new \LogicException('Role reference could not be resolved.');
            }

            foreach ($permissions as $permission) {
                $this->entityManager->persist(
                    new RolePermission()
                        ->setRole($role)
                        ->setPermission($permission),
                );
            }

            $this->entityManager->flush();
            $connection->commit();
            $this->recordChange($role, $before, $permissions);

            return new ApiResponse(success: true, message: 'Permisos asignados correctamente');
        } catch (\Throwable) {
            $connection->rollBack();

            return new ApiResponse(success: false, message: 'Error al asignar permisos');
        }
    }

    /**
     * @param list<string> $before
     * @param list<string> $after
     */
    private function recordChange(Role $role, array $before, array $after): void
    {
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));
        if ($added === [] && $removed === []) {
            return;
        }

        try {
            $parts = array_filter([
                $added === [] ? null : 'Agregó: ' . self::labels($added),
                $removed === [] ? null : 'Quitó: ' . self::labels($removed),
            ]);
            $this->activity->record(
                ActivityType::ROLE_PERMISSIONS_CHANGED,
                sprintf('Se cambiaron los permisos del rol %s', $role->getName()),
                implode(' · ', $parts),
                $role->getName(),
            );
        } catch (\Throwable) {
            // Recording is best effort and must never turn a saved change into an error.
        }
    }

    /** @param list<string> $keys */
    private static function labels(array $keys): string
    {
        $shown = array_map(PermissionCatalog::label(...), array_slice($keys, 0, 3));
        $rest = count($keys) - count($shown);

        return implode(', ', $shown) . ($rest > 0 ? sprintf(' y %d más', $rest) : '');
    }
}
