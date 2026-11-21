import { useState, type RefObject } from 'react';
import { Alert, Stack, Tabs } from '@nubitio/ui';
import './RolePermissionsPanel.scss';
import { PermissionTreeView, type PermissionTreeHandle } from './PermissionTreeView';
import { PermissionMatrix, type PermissionMatrixHandle } from './PermissionMatrix';

type TabId = 'menus' | 'actions';

type RolePermissionsPanelProps = {
  treeRef: RefObject<PermissionTreeHandle | null>;
  matrixRef: RefObject<PermissionMatrixHandle | null>;
};

/**
 * Content of the "Permisos" dialog on the Roles page: menu visibility
 * (tree) and granular action permissions (tree by module). Both stay
 * mounted so their imperative handles survive tab switches.
 */
export const RolePermissionsPanel = ({ treeRef, matrixRef }: RolePermissionsPanelProps) => {
  const [tab, setTab] = useState<TabId>('menus');

  return (
    <Tabs<TabId>
      className="role-permissions"
      aria-label="Tipos de permiso"
      value={tab}
      onChange={setTab}
      keepMounted
      items={[
        {
          value: 'menus',
          label: 'Menús',
          content: (
            <div className="role-permissions__pane">
              <PermissionTreeView ref={treeRef} />
            </div>
          ),
        },
        {
          value: 'actions',
          label: 'Acciones',
          content: (
            <Stack gap={3} className="role-permissions__pane">
              <Alert tone="info">
                Los permisos de acción definen lo que puede hacer cada usuario del rol, aunque vea
                el menú. Marca un módulo para conceder todos sus permisos.
              </Alert>
              <PermissionMatrix ref={matrixRef} />
            </Stack>
          ),
        },
      ]}
    />
  );
};
