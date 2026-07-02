<?php

declare(strict_types=1);

namespace App\Settings\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Settings\Dto\MenuDto;
use App\Settings\Dto\SubMenuDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;

/**
 * @implements ProviderInterface<object>
 */
readonly class MenuProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
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
                        $items[] = new SubMenuDto($child['key_name'], $child['route']);
                    }
                }

                yield new MenuDto($parent['key_name'], $parent['icon'], '', $items);
            } elseif (0 == $parent['parent_id'] && !empty($parent['route'])) {
                yield new MenuDTO($parent['key_name'], $parent['icon'], $parent['route']);
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
        // Sort by: parent group order → parent before children → child order_index.
        // COALESCE(p.order_index, m.order_index) places each child under its parent's
        // group slot; m.parent_id IS NOT NULL puts headings before their children.
        if (0 === $roleId) {
            return $this->connection->fetchAllAssociative('
                    SELECT m.*
                    FROM menu m
                    LEFT JOIN menu p ON p.id = m.parent_id
                    ORDER BY
                        COALESCE(p.order_index, m.order_index),
                        m.parent_id IS NOT NULL,
                        m.order_index
                ');
        }

        return $this->connection->fetchAllAssociative('
                    SELECT m.*
                    FROM menu m
                    LEFT JOIN menu p ON p.id = m.parent_id
                        INNER JOIN menu_rol mr ON mr.menu_id = m.id
                    WHERE mr.role_id = ?
                    ORDER BY
                        COALESCE(p.order_index, m.order_index),
                        m.parent_id IS NOT NULL,
                        m.order_index
                ', [$roleId]);
    }
}
