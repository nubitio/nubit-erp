<?php

namespace App\State\RoleMenu;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\RoleMenu\RoleMenuDto;
use App\Dto\RoleMenu\RoleMenuItemDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class RoleMenuProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        $roleId = $uriVariables['role_id'];

        $result = $this->connection->fetchAllAssociative(
            'SELECT
                m.menu_id,
                m.descripcion,
                m.parent_id,
                ifnull( mr.estado, 0 ) AS checked
            FROM
                menu m
                LEFT JOIN menu_rol mr ON m.menu_id = mr.menu_id
                AND mr.rol_id = ?
            ORDER BY
                m.menu_id,
                m.orden',
            [$roleId]
        );

        $data = [];
        $i = 0;
        foreach ($result as $menu) {
            if (0 == $menu['parent_id']) {
                $submenu = [];
                $j = 0;
                foreach ($result as $option) {
                    if ($menu['menu_id'] == $option['parent_id']) {
                        $submenu[$j] = new RoleMenuItemDto(
                            id: intval($option['menu_id']),
                            text: $option['descripcion'],
                            selected: (bool)$option['checked']
                        );
                        ++$j;
                    }
                }

                $data[$i] = new RoleMenuDto(
                    id: intval($menu['menu_id']),
                    text: $menu['descripcion'],
                    items: $submenu
                );
                ++$i;
            }
        }

        return $data;
    }
}
