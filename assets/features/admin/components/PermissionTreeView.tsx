import { forwardRef, useImperativeHandle, useMemo, useState } from 'react';
import { Text, TreeList, type TreeNodeId } from '@nubitio/ui';

import {
  countOn,
  initialMenuSelection,
  menuNodes,
  normalizeMenuSelection,
  selectedMenuIds,
  type PermissionNode,
  type RoleMenu,
} from './permissionSelection';

export type { RoleMenu } from './permissionSelection';

export interface PermissionTreeHandle {
  setItems: (items: RoleMenu[]) => void;
  getSelectedPermissionIds: () => unknown[];
}

/** Which menus (and the entries inside them) a role can see. */
export const PermissionTreeView = forwardRef<PermissionTreeHandle>((_props, ref) => {
  const [menus, setMenus] = useState<RoleMenu[]>([]);
  const [selected, setSelected] = useState<ReadonlySet<TreeNodeId>>(() => new Set());
  // A new role starts with every menu folded: remounting the tree resets what it remembers.
  const [generation, setGeneration] = useState(0);

  useImperativeHandle(ref, () => ({
    setItems: (next) => {
      setMenus(next);
      setSelected(initialMenuSelection(next));
      setGeneration((current) => current + 1);
    },
    getSelectedPermissionIds: () => selectedMenuIds(menus, selected),
  }));

  const nodes = useMemo(() => menuNodes(menus), [menus]);
  const entriesOf = useMemo(() => new Map(menus.map((menu) => [menu.id, menu.items])), [menus]);

  return (
    <TreeList<PermissionNode>
      key={generation}
      ariaLabel="Permisos de menú"
      testId="role-menu-tree"
      density="compact"
      showBorders={false}
      highlightSelectedRows={false}
      recursiveSelection
      maxHeight="50vh"
      filterPlaceholder="Buscar menú"
      items={nodes}
      getId={(node) => node.id}
      getParentId={(node) => node.parentId}
      selectionMode="multiple"
      selectedIds={selected}
      onSelectedIdsChange={(ids) => setSelected(normalizeMenuSelection(menus, ids))}
      columns={[
        {
          id: 'label',
          header: 'Menú',
          value: (node) => node.label,
          render: (node) => {
            const entries = entriesOf.get(node.id as number) ?? [];
            return (
              <>
                {node.label}
                {entries.length > 0 && (
                  <Text tone="muted">
                    {' '}
                    · {countOn(entries, selected)}/{entries.length}
                  </Text>
                )}
              </>
            );
          },
        },
      ]}
    />
  );
});

PermissionTreeView.displayName = 'PermissionTreeView';
