import { useMemo, useRef } from 'react';
import { SmartCrudPage, DialogView, defineResource, type ResourceToolbarContext } from '@nubitio/crud';
import { permissionEvents } from '@app/domainEvents';
import { httpClient } from '@shared/api/HttpClient';
import { roleEndpoints } from './api/roleEndpoints';
import { RoleFields } from './RoleFields';
import toast from '@shared/lib/SharedUtils';
import { useRoleObservers } from './model/useRoleObservers';
import { useEvents } from '@nubitio/core';
import {
  PermissionTreeView,
  type PermissionTreeHandle,
  type RoleMenu,
} from './components/PermissionTreeView';

type SelectedRole = {
  id: string | number;
  name?: string;
};

const ROLE_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['name'],
  },
];

const baseResource = defineResource(roleEndpoints.list(), {
  id: 'roles',
  title: 'Roles',
  fields: RoleFields(),
  viewMode: 'dialog',
  defaultPreset: 'basic',
  columnPresets: ROLE_COLUMN_PRESETS,
  emptyState: {
    title: 'Sin roles configurados',
    description: 'Crea un rol y asigna permisos desde la barra de herramientas.',
    icon: 'shield',
  },
  permissions: { canAdd: true, canEdit: true, canDelete: true },
});

export const RolePage = () => {
  const [on, emit] = useEvents();
  const treeRef = useRef<PermissionTreeHandle>(null);

  const resource = useMemo(
    () => ({
      ...baseResource,
      toolbar: ({ selectedRow }: ResourceToolbarContext) => ({
        selection: [
          {
            text: 'Permisos',
            type: 'info',
            group: 'security',
            groupLabel: 'Seguridad',
            onClick: () => {
              const row = selectedRow as SelectedRole | undefined;
              if (row) {
                emit(permissionEvents.ADD, { title: 'Permisos de ' + row.name, row });
                httpClient.get<RoleMenu[]>(roleEndpoints.menu(row.id)).then((response) => {
                  treeRef.current?.setItems(response.data);
                });
              } else {
                toast('Debe seleccionar un rol', 'error');
              }
            },
          },
        ],
      }),
    }),
    [emit],
  );

  useRoleObservers({ on, emit, permissionEvents, treeRef });

  return (
    <>
      <SmartCrudPage resource={resource} />
      <DialogView events={permissionEvents}>
        <PermissionTreeView ref={treeRef} />
      </DialogView>
    </>
  );
};
