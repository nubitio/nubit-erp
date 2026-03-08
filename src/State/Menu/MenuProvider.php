<?php

declare(strict_types=1);

namespace App\State\Menu;

use Override;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Menu\MenuDto;
use App\Dto\Menu\SubMenuDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * @implements ProviderInterface<object>
 */
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
    #[Override]
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
            if (0 == $parent['parent_id'] && empty($parent['route'])) {
                $items = [];

                foreach ($data as $child) {
                    if ($child['parent_id'] == $parent['id']) {
                        $items[] = new SubMenuDto(
                            $child['key_name'],
                            $child['route']
                        );
                    }
                }

                yield new MenuDto(
                    $parent['key_name'],
                    $parent['icon'],
                    '',
                    $items
                );
            } elseif (0 == $parent['parent_id'] && !empty($parent['route'])) {
                yield new MenuDTO(
                    $parent['key_name'],
                    $parent['icon'],
                    $parent['route']
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
        if (0 === $roleId) {
            return $this->connection->fetchAllAssociative(
                '
                    SELECT
                        m.*
                    FROM menu m
                    ORDER BY id, order_index
                '
            );
        }

        return $this->connection->fetchAllAssociative(
            '
                    SELECT
                        m.*
                    FROM menu m
                        INNER JOIN menu_rol mr ON mr.menu_id = m.id
                    WHERE mr.role_id = ?
                    ORDER BY m.id, order_index
                ',
            [$roleId]
        );
    }
}
