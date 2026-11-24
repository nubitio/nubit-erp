<?php

declare(strict_types=1);

namespace App\Settings\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Settings\Capability\TenantMenuAvailabilityPolicy;
use App\Settings\Dto\MenuDto;
use App\Settings\Dto\SubMenuDto;
use App\Settings\Entity\Menu;
use App\Settings\Runtime\TenantRuntimeConfigProvider;
use Doctrine\ORM\EntityManagerInterface;
use Override;

/**
 * @implements ProviderInterface<object>
 */
readonly class MenuProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?TenantRuntimeConfigProvider $runtimeConfigProvider = null,
        private TenantMenuAvailabilityPolicy $availabilityPolicy = new TenantMenuAvailabilityPolicy(),
    ) {}

    /**
     * @inheritdoc
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Never return the raw role menu when commercial/vertical policy cannot
        // be resolved. A broad role assignment is not an authorization policy.
        if ($this->runtimeConfigProvider === null) {
            return [];
        }

        return $this->buildMenuForRole(intval($uriVariables['roleId']), $this->runtimeConfigProvider->getConfig());
    }

    /**
     * @return iterable<MenuDTO>
     * @param array<string, mixed>|null $runtimeConfig
     */
    public function buildMenuForRole(int $roleId, ?array $runtimeConfig = null): iterable
    {
        $menus = $this->getMenuForRole($roleId);

        foreach ($menus as $parent) {
            $route = $parent->getRoute();
            if ($parent->getParent() === null && $route === null) {
                $items = [];

                foreach ($menus as $child) {
                    if (
                        $child->getParent()?->getId() === $parent->getId()
                        && (
                            $runtimeConfig === null
                            || $this->availabilityPolicy->allows((string) $child->getRoute(), $runtimeConfig)
                        )
                    ) {
                        $items[] = new SubMenuDto($child->getKeyName(), $this->absolutePath($child->getRoute()));
                    }
                }

                if ($items === []) {
                    continue;
                }

                yield new MenuDto($parent->getKeyName(), $parent->getIcon() ?? '', '', $items);
            } elseif (
                $parent->getParent() === null
                && is_string($route)
                && ($runtimeConfig === null || $this->availabilityPolicy->allows($route, $runtimeConfig))
            ) {
                yield new MenuDto($parent->getKeyName(), $parent->getIcon() ?? '', $this->absolutePath($route));
            }
        }
    }

    private function absolutePath(?string $route): string
    {
        if ($route === null || $route === '') {
            return '';
        }

        return str_starts_with($route, '/') ? $route : '/' . $route;
    }

    /**
     * @return list<Menu>
     */
    public function getMenuForRole(int $roleId): array
    {
        // Sort by: parent group order → parent before children → child order_index.
        // The CASE expression places each child under its parent's
        // group slot; the CASE expression puts headings before their children.
        if (0 === $roleId) {
            /** @var list<Menu> */
            return $this->entityManager->createQuery(<<<'DQL'
                SELECT m
                FROM App\Settings\Entity\Menu m
                LEFT JOIN m.parent p
                ORDER BY CASE WHEN p.id IS NULL THEN m.orderIndex ELSE p.orderIndex END, CASE WHEN m.parent IS NULL THEN 0 ELSE 1 END, m.orderIndex
                DQL)->getResult();
        }

        /** @var list<Menu> */
        return $this->entityManager
            ->createQuery(<<<'DQL'
                SELECT m
                FROM App\Settings\Entity\Menu m
                LEFT JOIN m.parent p
                INNER JOIN App\Settings\Entity\MenuRol mr WITH mr.menu = m
                WHERE mr.role = :roleId
                ORDER BY CASE WHEN p.id IS NULL THEN m.orderIndex ELSE p.orderIndex END, CASE WHEN m.parent IS NULL THEN 0 ELSE 1 END, m.orderIndex
                DQL)
            ->setParameter('roleId', $roleId)
            ->getResult();
    }
}
