import { useMemo, useRef } from 'react';
import { SmartCrudPage, DialogView, defineResource, type ResourceToolbarContext } from '@nubit/crud';
import { permissionEvents, roleEvents } from '@app/domainEvents';
import { httpClient } from '@shared/api/HttpClient';
import { roleEndpoints } from '../api/roleEndpoints';
import { RoleFields } from './RoleFields';
import toast from '@shared/lib/SharedUtils';
import { useRoleObservers } from '../model/useRoleObservers';
import { useEvents } from '@nubit/core';
import {
  PermissionTreeView,
  type PermissionTreeHandle,
  type RoleMenu,
} from '../components/PermissionTreeView';

type SelectedRole = {
  id: string | number;
  name?: string;
};

const baseResource = defineResource({
  id: 'roles',
  title: 'Roles',
  apiUrl: roleEndpoints.list(),
  fields: RoleFields(),
  events: roleEvents,
  permissions: { canAdd: true, canEdit: true, canDelete: true },
});

export const RolePage = () => {
  const [on, emit] = useEvents();
  const treeRef = useRef<PermissionTreeHandle>(null);

  const resource = useMemo(
    () => ({
      ...baseResource,
      toolbar: ({ selectedRow }: ResourceToolbarContext) => [
        {
          text: 'Permisos',
          type: 'info',
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
