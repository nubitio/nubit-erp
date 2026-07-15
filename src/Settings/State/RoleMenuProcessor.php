<?php

declare(strict_types=1);

namespace App\Settings\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Entity\Role;
use App\Settings\ApiResource\RoleMenuResource;
use App\Settings\Entity\Menu;
use App\Settings\Entity\MenuRol;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Http\ApiResponse;
use Override;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
readonly class RoleMenuProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @inheritdoc
     *
     * @param RoleMenuResource $data
     *
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        $roleId = $data->roleId;

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $this->entityManager
                ->createQuery('DELETE FROM App\\Settings\\Entity\\MenuRol mr WHERE mr.role = :roleId')
                ->setParameter('roleId', $roleId)
                ->execute();

            $role = $this->entityManager->getReference(Role::class, $roleId);
            if (!$role instanceof Role) {
                throw new \LogicException('Role reference could not be resolved.');
            }

            foreach ($data->permissions as $menuId) {
                $menu = $this->entityManager->getReference(Menu::class, $menuId);
                if (!$menu instanceof Menu) {
                    throw new \LogicException('Menu reference could not be resolved.');
                }

                $this->entityManager->persist(
                    (new MenuRol())
                        ->setMenu($menu)
                        ->setRole($role),
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
