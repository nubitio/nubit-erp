<?php

namespace App\Core\Infrastructure\Service;

use Doctrine\DBAL\Connection;

class MenuBuilder
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function buildMenuForRole(int $roleId): array
    {
        $data = $this->getMenuForRole($roleId);
        $menu = [];
        $i = 0;
        foreach ($data as $parent) {
            if (0 == $parent['parent_id'] && '' == $parent['link']) {
                $items = [];
                $j = 0;
                foreach ($data as $child) {
                    if ($parent['menu_id'] == $child['parent_id']) {
                        $items[$j] = ['text' => $child['descripcion'], 'iconCls' => $child['icon'], 'url' => $child['link']];
                        ++$j;
                    }
                }
                $menu[$i] = ['text' => $parent['descripcion'], 'iconCls' => $parent['icon'], 'children' => $items];
                ++$i;
            } else {
                if (0 == $parent['parent_id'] && '' != $parent['link']) {
                    $menu[$i] = ['text' => $parent['descripcion'], 'iconCls' => $parent['icon'], 'url' => $parent['link']];
                    ++$i;
                }
            }
        }

        return $menu;
    }

    public function getMenuForRole(int $roleId): array
    {
        if (0 == $roleId) {
            $result = $this->connection->fetchAllAssociative('
                    SELECT
                        m.*
                    FROM menu m
                    ORDER BY menu_id, orden
                ');
        } else {
            $result = $this->connection->fetchAllAssociative('
                    SELECT
                        m.*
                    FROM menu m
                        INNER JOIN menu_rol mr ON mr.menu_id = m.menu_id
                    WHERE mr.rol_id = ? AND mr.estado = 1
                    ORDER BY menu_id, orden
                ', [$roleId]);
        }

        return $result;
    }
}
