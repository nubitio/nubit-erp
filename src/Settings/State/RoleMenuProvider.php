<?php

declare(strict_types=1);

namespace App\Settings\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Settings\Dto\RoleMenuDto;
use App\Settings\Dto\RoleMenuItemDto;
use App\Settings\Entity\Menu;
use Doctrine\ORM\EntityManagerInterface;
use Override;

/**
 * @implements ProviderInterface<object>
 */
readonly class RoleMenuProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @inheritdoc
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $roleId = (int) $uriVariables['role_id'];

        /** @var list<Menu> $menus */
        $menus = $this->entityManager
            ->createQuery('SELECT m FROM App\\Settings\\Entity\\Menu m ORDER BY m.id, m.orderIndex')
            ->getResult();
        $assignedMenuIds = $this->entityManager
            ->createQuery('SELECT IDENTITY(mr.menu) FROM App\\Settings\\Entity\\MenuRol mr WHERE mr.role = :roleId')
            ->setParameter('roleId', $roleId)
            ->getSingleColumnResult();
        $assigned = array_flip(array_map(intval(...), $assignedMenuIds));

        $data = [];
        foreach ($menus as $menu) {
            if ($menu->getParent() === null) {
                $submenu = [];
                $menuId = $menu->getId();
                if ($menuId === null) {
                    continue;
                }

                foreach ($menus as $option) {
                    $optionId = $option->getId();
                    if ($option->getParent()?->getId() === $menuId && $optionId !== null) {
                        $submenu[] = new RoleMenuItemDto(
                            id: $optionId,
                            text: $option->getKeyName(),
                            selected: array_key_exists($optionId, $assigned),
                        );
                    }
                }

                $data[] = new RoleMenuDto(
                    id: $menuId,
                    text: $menu->getKeyName(),
                    items: $submenu,
                    selected: array_key_exists($menuId, $assigned),
                );
            }
        }

        return $data;
    }
}
