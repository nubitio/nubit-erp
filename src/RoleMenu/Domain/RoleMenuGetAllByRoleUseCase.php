<?php

namespace App\RoleMenu\Domain;

class RoleMenuGetAllByRoleUseCase
{
    private RoleMenuRepositoryInterface $repository;

    public function __construct(RoleMenuRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<int, mixed>
     */
    public function __invoke(int $roleId): array
    {
        /** @var array<int, array<string, string>> $result */
        $result = $this->repository->getAllByRole($roleId);
        $data = [];
        $i = 0;
        foreach ($result as $menu) {
            if (0 == $menu['parent_id']) {
                $submenu = [];
                $j = 0;
                foreach ($result as $option) {
                    if ($menu['menu_id'] == $option['parent_id']) {
                        $submenu[$j] = ['id' => $option['menu_id'], 'text' => $option['descripcion'], 'checked' => (bool) $option['checked']];
                        ++$j;
                    }
                }
                $data[$i] = ['id' => $menu['menu_id'], 'text' => $menu['descripcion'], 'children' => $submenu];
                ++$i;
            }
        }

        return $data;
    }
}
