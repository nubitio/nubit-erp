<?php

namespace App\State\Menu;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Menu\MenuDto;
use App\Dto\Menu\SubMenuDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class MenuProvider implements ProviderInterface
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
        return $this->buildMenuForRole(intval($uriVariables['roleId']));
    }

    /**
     * @return iterable<MenuDTO>
     *
     * @throws Exception
     */
    public function buildMenuForRole(int $roleId): iterable
    {
        $data = $this->getMenuForRole($roleId);

        foreach ($data as $parent) {
            if (0 == $parent['parent_id'] && empty($parent['link'])) {
                $items = [];

                foreach ($data as $child) {
                    if ($child['parent_id'] == $parent['menu_id']) {
                        $items[] = new SubMenuDto(
                            $child['descripcion'],
                            $child['link']
                        );
                    }
                }

                yield new MenuDto(
                    $parent['descripcion'],
                    $parent['icon'],
                    '',
                    $items
                );
            } elseif (0 == $parent['parent_id'] && !empty($parent['link'])) {
                yield new MenuDTO(
                    $parent['descripcion'],
                    $parent['icon'],
                    $parent['link']
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getMenuForRole(int $roleId): array
    {
        if (0 == $roleId) {
            return $this->connection->fetchAllAssociative(
                '
                    SELECT
                        m.*
                    FROM menu m
                    ORDER BY menu_id, orden
                '
            );
        }

        return $this->connection->fetchAllAssociative(
            '
                    SELECT
                        m.*
                    FROM menu m
                        INNER JOIN menu_rol mr ON mr.menu_id = m.menu_id
                    WHERE mr.rol_id = ? AND mr.estado = 1
                    ORDER BY menu_id, orden
                ',
            [$roleId]
        );
    }
}
