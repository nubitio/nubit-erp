import './RolePage.scss';
import { roleEndpoints } from '../api/roleEndpoints';
import { useEvents } from '@core/event/EventHook';
import { DxDataGrid, DxDataGridEvents } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { permissionEvents, roleEvents } from '@features/role';
import { httpClient } from '@shared/api/HttpClient';
import { RoleFields } from './RoleFields';
import { useMemo, useRef } from 'react';
import toast from '@shared/lib/SharedUtils';
import TreeView, { TreeViewRef } from 'devextreme-react/tree-view';
import DataSource from 'devextreme/data/data_source';
import ArrayStore from 'devextreme/data/array_store';
import { useRoleObservers } from '../model/useRoleObservers';

type RoleMenu = {
  id: number;
  text: string;
  items: MenuItem[];
};

type MenuItem = {
  id: number;
  text: string;
  selected: boolean;
};

export const RolePage = () => {
  const [on, emit] = useEvents();
  const container = 'wrapper';
  const fields = useMemo(() => RoleFields(), []);
  const gridRef = useRef<DxDataGridEvents>(null);
  const treeRef = useRef<TreeViewRef>(null);
  const permissionsData = useRef<DataSource>(
    new DataSource({
      store: new ArrayStore({
        key: 'id',
        data: [],
      }),
    }),
  );

  const API_URL = roleEndpoints.list();

  const toolbar = [
    {
      text: 'Permisos',
      type: 'info',
      onClick: () => {
        const row = gridRef.current?.getSelectedRow();
        if (row) {
          emit(permissionEvents.ADD, { title: 'Permisos de ' + row.name });
          httpClient.get<RoleMenu[]>(roleEndpoints.menu(row.id)).subscribe((response) => {
            const store = permissionsData.current.store() as ArrayStore;
            store.clear();
            response.data.forEach((item: any) => {
              store.insert(item);
            });
            permissionsData.current.reload();
          });
        } else {
          toast('Debe seleccionar un rol', 'error');
        }
      },
    },
  ];

  useRoleObservers({
    on,
    emit,
    permissionEvents,
    gridRef,
    treeRef,
  });

  return (
    <div id={container}>
      <DxDataGrid
        id={'roles'}
        ref={gridRef}
        title={'Roles'}
        url={API_URL}
        allowAdd={true}
        allowEdit={true}
        allowDelete={true}
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
          dataSource={permissionsData.current}
          showCheckBoxesMode={'normal'}
          selectByClick={true}
        />
      </DxDialog>
    </div>
  );
};
