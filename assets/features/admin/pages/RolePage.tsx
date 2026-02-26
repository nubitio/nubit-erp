import './RolePage.scss';
import { Constants } from '@platform/common/Constants';
import { useEvents } from '@platform/event/EventHook';
import { DxDataGrid, DxDataGridEvents } from '@platform/datagrid/DxDataGrid';
import { DxDialog } from '@platform/dialog/DxDialog';
import { DxForm } from '@platform/form/DxForm';
import { permissionEvents, roleEvents } from '@features/role';
import AppResponse from '@shared/api/AppResponse';
import { httpClient } from '@shared/api/HttpClient';
import { RoleFields } from './RoleFields';
import { useEffect, useRef } from 'react';
import notify from 'devextreme/ui/notify';
import TreeView, { TreeViewRef } from 'devextreme-react/tree-view';
import DataSource from 'devextreme/data/data_source';
import ArrayStore from 'devextreme/data/array_store';

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
  const fields = RoleFields();
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

  const API_URL = Constants.API_BASE_URL + 'roles';

  const toolbar = [
    {
      text: 'Permisos',
      type: 'info',
      onClick: () => {
        const row = gridRef.current?.getSelectedRow();
        if (row) {
          emit(permissionEvents.ADD, { title: 'Permisos de ' + row.name });
          httpClient
            .get<RoleMenu[]>(Constants.API_BASE_URL + `role-menu/${row.id}`)
            .subscribe((response) => {
              const store = permissionsData.current.store() as ArrayStore;
              store.clear();
              response.data.forEach((item: any) => {
                store.insert(item);
              });
              permissionsData.current.reload();
            });
        } else {
          notify(
            {
              message: 'Debe seleccionar un rol',
              type: 'error',
              displayTime: 3000,
            },
            { position: 'top right' },
          );
        }
      },
    },
  ];

  const setupEventsRef = useRef<() => void>(() => {});

  const setupEvents = () => {
    on(permissionEvents.SAVE, () => {
      const row = gridRef.current?.getSelectedRow();
      if (row) {
        const data = treeRef.current?.instance().getSelectedNodes();
        let permissions: any[] = [];
        data?.forEach((item) => {
          if (item.parent?.key) {
            permissions.push(item.parent.key);
          }
          permissions.push(item.key);
        });
        permissions = [...new Set(permissions)];
        httpClient
          .post<AppResponse>(Constants.API_BASE_URL + 'role-menu', {
            roleId: row.id,
            permissions: permissions,
          })
          .subscribe((response) => {
            notify(
              {
                message: response.data.message,
                type: 'success',
                displayTime: 3000,
              },
              { position: 'top right' },
            );
            emit(permissionEvents.SUCCESS);
          });
      }
    });
  };

  setupEventsRef.current = setupEvents;

  useEffect(() => {
    setupEventsRef.current();
  }, []);

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
