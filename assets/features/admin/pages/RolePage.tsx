import { useRef } from 'react';
import { DataGridView, DialogView, FormView, type GridHandle } from '@nubit/crud';
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

const API_URL = roleEndpoints.list();
const fields = RoleFields();

export const RolePage = () => {
  const [on, emit] = useEvents();
  const gridRef = useRef<GridHandle>(null);
  const treeRef = useRef<PermissionTreeHandle>(null);

  const toolbar = [
    {
      text: 'Permisos',
      type: 'info',
      onClick: () => {
        const row = gridRef.current?.getSelectedRow() as SelectedRole | undefined;
        if (row) {
          emit(permissionEvents.ADD, { title: 'Permisos de ' + row.name });
          httpClient.get<RoleMenu[]>(roleEndpoints.menu(row.id)).then((response) => {
            treeRef.current?.setItems(response.data);
          });
        } else {
          toast('Debe seleccionar un rol', 'error');
        }
      },
    },
  ];

  useRoleObservers({ on, emit, permissionEvents, gridRef, treeRef });

  return (
    <div id="wrapper">
      <DataGridView
        id="roles"
        ref={gridRef}
        title="Roles"
        url={API_URL}
        allowAdd
        allowEdit
        allowDelete
        toolbar={toolbar}
        fields={fields}
        events={roleEvents}
      />
      <DialogView events={roleEvents}>
        <FormView url={API_URL} fields={fields} events={roleEvents} />
      </DialogView>
      <DialogView events={permissionEvents}>
        <PermissionTreeView ref={treeRef} />
      </DialogView>
    </div>
  );
};
