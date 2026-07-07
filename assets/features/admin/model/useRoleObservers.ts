import { useEffect, useRef } from 'react';
import type { DataRecord } from '@nubitio/crud';
import { httpClient } from '@shared/api/HttpClient';
import { roleEndpoints } from '../api/roleEndpoints';
import AppResponse from '@shared/api/AppResponse';
import toast from '@shared/lib/SharedUtils';
import type { PermissionTreeHandle } from '../components/PermissionTreeView';
import type { PermissionMatrixHandle } from '../components/PermissionMatrix';

type OnEventFn = <T>(name: string, handler: (payload: T) => void) => unknown;

type UseRoleObserversParams = {
  on: OnEventFn;
  emit: <T>(name: string, payload?: T) => void;
  permissionEvents: {
    ADD: string;
    SAVE: string;
    SUCCESS: string;
  };
  treeRef: React.RefObject<PermissionTreeHandle | null>;
  matrixRef: React.RefObject<PermissionMatrixHandle | null>;
};

export const useRoleObservers = ({
  on,
  emit,
  permissionEvents,
  treeRef,
  matrixRef,
}: UseRoleObserversParams) => {
  const currentRoleRef = useRef<DataRecord | null>(null);
  const callbacksRef = useRef({ on, emit, permissionEvents, treeRef, matrixRef });
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix
  callbacksRef.current = { on, emit, permissionEvents, treeRef, matrixRef };

  useEffect(() => {
    const {
      on: onEvent,
      emit: emitEvent,
      permissionEvents: ev,
      treeRef: tree,
      matrixRef: matrix,
    } = callbacksRef.current;

    onEvent<{ row?: DataRecord } | undefined>(ev.ADD, (payload) => {
      currentRoleRef.current = payload?.row ?? null;
    });

    onEvent(ev.SAVE, () => {
      const row = currentRoleRef.current;
      if (!row) return;
      const menuIds = tree.current?.getSelectedPermissionIds() ?? [];
      const permissionKeys = matrix.current?.getGrantedKeys() ?? [];
      Promise.all([
        httpClient.post<AppResponse>(roleEndpoints.saveMenu(), {
          roleId: row.id,
          permissions: menuIds,
        }),
        httpClient.post<AppResponse>(roleEndpoints.savePermissions(), {
          roleId: row.id,
          permissions: permissionKeys,
        }),
      ])
        .then(([menuResponse]) => {
          toast(menuResponse.data.message, 'success');
          emitEvent(ev.SUCCESS);
        })
        .catch(() => {
          toast('No se pudieron guardar los permisos', 'error');
        });
    });
  }, []);
};
