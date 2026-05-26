import { useEffect, useRef } from 'react';
import type { DataRecord } from '@nubit/crud';
import { httpClient } from '@shared/api/HttpClient';
import { roleEndpoints } from '../api/roleEndpoints';
import AppResponse from '@shared/api/AppResponse';
import toast from '@shared/lib/SharedUtils';
import type { PermissionTreeHandle } from '../components/PermissionTreeView';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UseRoleObserversParams = {
  on: OnEventFn;
  emit: <T>(name: string, payload?: T) => void;
  permissionEvents: {
    SAVE: string;
    SUCCESS: string;
  };
  selectedRowRef: React.RefObject<DataRecord | null>;
  treeRef: React.RefObject<PermissionTreeHandle | null>;
};

export const useRoleObservers = ({
  on,
  emit,
  permissionEvents,
  selectedRowRef,
  treeRef,
}: UseRoleObserversParams) => {
  const callbacksRef = useRef({ on, emit, permissionEvents, selectedRowRef, treeRef });
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix
  callbacksRef.current = { on, emit, permissionEvents, selectedRowRef, treeRef };

  useEffect(() => {
    const {
      on: onEvent,
      emit: emitEvent,
      permissionEvents: ev,
      selectedRowRef: rowRef,
      treeRef: tree,
    } = callbacksRef.current;

    onEvent(ev.SAVE, () => {
      const row = rowRef.current;
      if (!row) return;
      const permissions = tree.current?.getSelectedPermissionIds() ?? [];
      httpClient
        .post<AppResponse>(roleEndpoints.saveMenu(), {
          roleId: row.id,
          permissions,
        })
        .then((response) => {
          toast(response.data.message, 'success');
          emitEvent(ev.SUCCESS);
        })
        .catch(() => {
          toast('No se pudieron guardar los permisos', 'error');
        });
    });
  }, []);
};
