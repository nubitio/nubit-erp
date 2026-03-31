import { useRef, useState } from 'react';
import { DxDataGrid, type DxDataGridEvents } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { permissionEvents, roleEvents } from '@features/role';
import { httpClient } from '@shared/api/HttpClient';
import { roleEndpoints } from '../api/roleEndpoints';
import { RoleFields } from './RoleFields';
import toast from '@shared/lib/SharedUtils';
import TreeView, { type TreeViewRef } from 'devextreme-react/tree-view';
import DataSource from 'devextreme/data/data_source';
import ArrayStore from 'devextreme/data/array_store';
import { useRoleObservers } from '../model/useRoleObservers';
import { useEvents } from '@core/event/EventHook';

type RoleMenu = {
  id: number;
  text: string;
  items: { id: number; text: string; selected: boolean }[];
};

type SelectedRole = {
  id: string | number;
  name?: string;
};

const API_URL = roleEndpoints.list();
const fields = RoleFields();

export const RolePage = () => {
  const [on, emit] = useEvents();
  const gridRef = useRef<DxDataGridEvents>(null);
  const treeRef = useRef<TreeViewRef>(null);
  const [permissionsData] = useState(
    () => new DataSource({ store: new ArrayStore({ key: 'id', data: [] }) }),
  );

  const toolbar = [
    {
      text: 'Permisos',
      type: 'info',
      onClick: () => {
        const row = gridRef.current?.getSelectedRow() as SelectedRole | undefined;
        if (row) {
          emit(permissionEvents.ADD, { title: 'Permisos de ' + row.name });
          httpClient.get<RoleMenu[]>(roleEndpoints.menu(row.id)).subscribe((response) => {
            const store = permissionsData.store() as ArrayStore;
            store.clear();
            response.data.forEach((item: RoleMenu) => store.insert(item));
            permissionsData.reload();
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
      <DxDataGrid
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
      <DxDialog events={roleEvents}>
        <DxForm url={API_URL} fields={fields} events={roleEvents} />
      </DxDialog>
      <DxDialog events={permissionEvents}>
        <TreeView
          ref={treeRef}
          dataSource={permissionsData}
          showCheckBoxesMode="normal"
          selectByClick={true}
        />
      </DxDialog>
    </div>
  );
};
