import { useEffect, useRef } from 'react';
import { DxDataGridEvents } from '@platform/datagrid/DxDataGrid';
import { TreeViewRef } from 'devextreme-react/tree-view';
import { httpClient } from '@shared/api/HttpClient';
import { roleEndpoints } from '../api/roleEndpoints';
import AppResponse from '@shared/api/AppResponse';
import toast from '@shared/lib/SharedUtils';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UseRoleObserversParams = {
  on: OnEventFn;
  emit: <T>(name: string, payload?: T) => void;
  permissionEvents: {
    SAVE: string;
    SUCCESS: string;
  };
  gridRef: React.RefObject<DxDataGridEvents | null>;
  treeRef: React.RefObject<TreeViewRef | null>;
};

export const useRoleObservers = ({
  on,
  emit,
  permissionEvents,
  gridRef,
  treeRef,
}: UseRoleObserversParams) => {
  const callbacksRef = useRef({ on, emit, permissionEvents, gridRef, treeRef });
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix
  callbacksRef.current = { on, emit, permissionEvents, gridRef, treeRef };

  useEffect(() => {
    const {
      on: onEvent,
      emit: emitEvent,
      permissionEvents: ev,
      gridRef: grid,
      treeRef: tree,
    } = callbacksRef.current;

    onEvent(ev.SAVE, () => {
      const row = grid.current?.getSelectedRow();
      if (!row) return;
      const data = tree.current?.instance().getSelectedNodes();
      let permissions: any[] = [];
      data?.forEach((item) => {
        if (item.parent?.key) {
          permissions.push(item.parent.key);
        }
        permissions.push(item.key);
      });
      permissions = [...new Set(permissions)];
      httpClient
        .post<AppResponse>(roleEndpoints.saveMenu(), {
          roleId: row.id,
          permissions,
        })
        .subscribe((response) => {
          toast(response.data.message, 'success');
          emitEvent(ev.SUCCESS);
        });
    });
  }, []);
};
