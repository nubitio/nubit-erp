<?php

namespace App\Core\Infrastructure\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class MenuBuilder
{
    public function __construct(
        private Connection $connection
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function buildMenuForRole(int $roleId): array
    {
        $data = $this->getMenuForRole($roleId);
        $menu = [];

        foreach ($data as $parent) {
            if ($parent['parent_id'] == 0 && empty($parent['link'])) {
                $items = [];

                foreach ($data as $child) {
                    if ($child['parent_id'] == $parent['menu_id']) {
                        $items[] = [
                            'text' => $child['descripcion'],
                            'path' => $child['link']
                        ];
                    }
                }

                $menu[] = [
                    'text' => $parent['descripcion'],
                    'icon' => $parent['icon'],
                    'items' => $items
                ];
            } else {
                if ($parent['parent_id'] == 0 && !empty($parent['link'])) {
                    $menu[] = [
                        'text' => $parent['descripcion'],
                        'icon' => $parent['icon'],
                        'path' => $parent['link']
                    ];
                }
            }
        }

        return $menu;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
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
