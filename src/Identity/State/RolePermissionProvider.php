<?php

declare(strict_types=1);

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Identity\Dto\RolePermissionItemDto;
use App\Identity\Dto\RolePermissionModuleDto;
use App\Identity\Repository\RolePermissionRepository;
use App\Identity\Security\PermissionCatalog;
use Override;

/**
 * Returns the full permission catalog grouped by module, with a `granted`
 * flag per permission for the requested role.
 *
 * @implements ProviderInterface<object>
 */
readonly class RolePermissionProvider implements ProviderInterface
{
    public function __construct(
        private RolePermissionRepository $rolePermissions,
    ) {}

    /**
     * @inheritdoc
     *
     * @return list<RolePermissionModuleDto>
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $roleId = intval($uriVariables['role_id']);
        $granted = $this->rolePermissions->findPermissionKeysByRoleId($roleId);

        $modules = [];
        foreach (PermissionCatalog::modules() as $moduleKey => $module) {
            $permissions = [];
            foreach ($module['permissions'] as $permissionKey => $label) {
                $permissions[] = new RolePermissionItemDto(
                    key: $permissionKey,
                    label: $label,
                    granted: in_array($permissionKey, $granted, strict: true),
                );
            }

            $modules[] = new RolePermissionModuleDto(
                key: $moduleKey,
                label: $module['label'],
                permissions: $permissions,
            );
        }

        return $modules;
    }
}
