import { forwardRef, useImperativeHandle, useRef, useState } from 'react';
import TreeView, { type TreeViewRef } from 'devextreme-react/tree-view';
import DataSource from 'devextreme/data/data_source';
import ArrayStore from 'devextreme/data/array_store';

export type RoleMenu = {
  id: number;
  text: string;
  items: { id: number; text: string; selected: boolean }[];
};

export interface PermissionTreeHandle {
  setItems: (items: RoleMenu[]) => void;
  getSelectedPermissionIds: () => unknown[];
}

export const PermissionTreeView = forwardRef<PermissionTreeHandle>((_props, ref) => {
  const treeRef = useRef<TreeViewRef>(null);
  const [dataSource] = useState(
    () => new DataSource({ store: new ArrayStore({ key: 'id', data: [] }) }),
  );

  useImperativeHandle(ref, () => ({
    setItems: (items) => {
      const store = dataSource.store() as ArrayStore;
      store.clear();
      items.forEach((item) => store.insert(item));
      dataSource.reload();
    },
    getSelectedPermissionIds: () => {
      const selectedNodes = treeRef.current?.instance().getSelectedNodes() ?? [];
      const permissions = selectedNodes.flatMap((item) => {
        const parentKey = item.parent?.key;
        return parentKey ? [parentKey, item.key] : [item.key];
      });
      return [...new Set(permissions)];
    },
  }));

  return (
    <TreeView
      ref={treeRef}
      dataSource={dataSource}
      showCheckBoxesMode="normal"
      selectByClick={true}
    />
  );
});
