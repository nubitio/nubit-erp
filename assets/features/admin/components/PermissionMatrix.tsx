import { forwardRef, useImperativeHandle, useMemo, useState } from 'react';
import { Text, TreeList, type TreeNodeId } from '@nubitio/ui';

import {
  countOn,
  grantedKeys,
  initialModuleSelection,
  moduleNodeId,
  moduleNodes,
  normalizeModuleSelection,
  type PermissionModule,
  type PermissionNode,
} from './permissionSelection';

export type { PermissionModule } from './permissionSelection';

export interface PermissionMatrixHandle {
  setModules: (modules: PermissionModule[]) => void;
  getGrantedKeys: () => string[];
}

/** What a role may do, grouped by module. The tree's own search filters by module or permission. */
export const PermissionMatrix = forwardRef<PermissionMatrixHandle>((_props, ref) => {
  const [modules, setModules] = useState<PermissionModule[]>([]);
  const [selected, setSelected] = useState<ReadonlySet<TreeNodeId>>(() => new Set());
  // Remounting the tree clears its search and expansion when another role is loaded.
  const [generation, setGeneration] = useState(0);

  useImperativeHandle(ref, () => ({
    setModules: (next) => {
      setModules(next);
      setSelected(initialModuleSelection(next));
      setGeneration((current) => current + 1);
    },
    getGrantedKeys: () => grantedKeys(modules, selected),
  }));

  const nodes = useMemo(() => moduleNodes(modules), [modules]);
  const permissionsOf = useMemo(
    () =>
      new Map(
        modules.map((module) => [
          moduleNodeId(module.key),
          module.permissions.map((permission) => ({ id: permission.key })),
        ]),
      ),
    [modules],
  );

  const total = modules.reduce((count, module) => count + module.permissions.length, 0);
  const granted = grantedKeys(modules, selected).length;

  return (
    <>
      <Text tone="muted" data-testid="role-actions-count">
        {granted} de {total} permisos
      </Text>
      <TreeList<PermissionNode>
        key={generation}
        ariaLabel="Permisos de acción"
        testId="role-actions-tree"
        density="compact"
        showBorders={false}
        highlightSelectedRows={false}
        recursiveSelection
        autoExpandAll
        maxHeight="50vh"
        filterPlaceholder="Buscar permiso"
        items={nodes}
        getId={(node) => node.id}
        getParentId={(node) => node.parentId}
        selectionMode="multiple"
        selectedIds={selected}
        onSelectedIdsChange={(ids) => setSelected(normalizeModuleSelection(modules, ids))}
        columns={[
          {
            id: 'label',
            header: 'Módulo y permiso',
            value: (node) => node.label,
            render: (node) => {
              const entries = permissionsOf.get(String(node.id)) ?? [];
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
    </>
  );
});

PermissionMatrix.displayName = 'PermissionMatrix';
