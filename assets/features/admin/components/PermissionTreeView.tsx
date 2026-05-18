import './PermissionTreeView.scss';
import { forwardRef, useImperativeHandle, useState } from 'react';

export type RoleMenu = {
  id: number;
  text: string;
  items: { id: number; text: string; selected: boolean }[];
};

export interface PermissionTreeHandle {
  setItems: (items: RoleMenu[]) => void;
  getSelectedPermissionIds: () => unknown[];
}

type TreeCheckboxProps = {
  checked: boolean;
  indeterminate?: boolean;
  label: string;
  onChange: () => void;
};

function TreeCheckbox({ checked, indeterminate = false, label, onChange }: TreeCheckboxProps) {
  return (
    <label className="permission-tree__checkbox">
      <input
        type="checkbox"
        checked={checked}
        className="permission-tree__input"
        ref={(element) => {
          if (element) element.indeterminate = indeterminate;
        }}
        onChange={onChange}
      />
      <span>{label}</span>
    </label>
  );
}

export const PermissionTreeView = forwardRef<PermissionTreeHandle>((_props, ref) => {
  const [items, setItemsState] = useState<RoleMenu[]>([]);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(() => new Set());
  const [expandedIds, setExpandedIds] = useState<Set<number>>(() => new Set());

  useImperativeHandle(ref, () => ({
    setItems: (nextItems) => {
      setItemsState(nextItems);
      setExpandedIds(
        new Set(
          nextItems
            .filter((menu, index) => index <= 1 && menu.items.length > 0)
            .map((menu) => menu.id),
        ),
      );
      setSelectedIds(
        new Set(
          nextItems.flatMap((menu) => [
            ...(menu.items.some((permission) => permission.selected) ? [menu.id] : []),
            ...menu.items
              .filter((permission) => permission.selected)
              .map((permission) => permission.id),
          ]),
        ),
      );
    },
    getSelectedPermissionIds: () => {
      const permissions = items.flatMap((menu) => {
        const selectedChildren = menu.items
          .filter((permission) => selectedIds.has(permission.id))
          .map((permission) => permission.id);

        return selectedChildren.length > 0 || selectedIds.has(menu.id)
          ? [menu.id, ...selectedChildren]
          : [];
      });

      return [...new Set(permissions)];
    },
  }));

  const toggleMenu = (menu: RoleMenu) => {
    const allChildrenSelected = menu.items.every((permission) => selectedIds.has(permission.id));
    const nextSelectedIds = new Set(selectedIds);

    if (allChildrenSelected) {
      nextSelectedIds.delete(menu.id);
      menu.items.forEach((permission) => nextSelectedIds.delete(permission.id));
    } else {
      nextSelectedIds.add(menu.id);
      menu.items.forEach((permission) => nextSelectedIds.add(permission.id));
    }

    setSelectedIds(nextSelectedIds);
  };

  const toggleExpanded = (menuId: number) => {
    const nextExpandedIds = new Set(expandedIds);

    if (nextExpandedIds.has(menuId)) {
      nextExpandedIds.delete(menuId);
    } else {
      nextExpandedIds.add(menuId);
    }

    setExpandedIds(nextExpandedIds);
  };

  const togglePermission = (menu: RoleMenu, permissionId: number) => {
    const nextSelectedIds = new Set(selectedIds);

    if (nextSelectedIds.has(permissionId)) {
      nextSelectedIds.delete(permissionId);
    } else {
      nextSelectedIds.add(permissionId);
    }

    const hasSelectedChild = menu.items.some((permission) => nextSelectedIds.has(permission.id));
    if (hasSelectedChild) {
      nextSelectedIds.add(menu.id);
    } else {
      nextSelectedIds.delete(menu.id);
    }

    setSelectedIds(nextSelectedIds);
  };

  return (
    <div className="permission-tree" role="tree" aria-label="Permisos">
      {items.map((menu) => {
        const hasChildren = menu.items.length > 0;
        const expanded = expandedIds.has(menu.id);
        const selectedChildrenCount = menu.items.filter((permission) =>
          selectedIds.has(permission.id),
        ).length;
        const allChildrenSelected =
          menu.items.length > 0 && selectedChildrenCount === menu.items.length;
        const someChildrenSelected = selectedChildrenCount > 0 && !allChildrenSelected;

        return (
          <div key={menu.id} className="permission-tree__item" role="treeitem" aria-expanded={expanded}>
            <div className="permission-tree__row">
              {hasChildren ? (
                <button
                  type="button"
                  className="permission-tree__toggle"
                  aria-label={`${expanded ? 'Contraer' : 'Expandir'} ${menu.text}`}
                  onClick={() => toggleExpanded(menu.id)}
                >
                  <i
                    className={`ph ${expanded ? 'ph-caret-down' : 'ph-caret-right'}`}
                    aria-hidden="true"
                  />
                </button>
              ) : (
                <span className="permission-tree__toggle-spacer" />
              )}
              <TreeCheckbox
                checked={allChildrenSelected || selectedIds.has(menu.id)}
                indeterminate={someChildrenSelected}
                label={menu.text}
                onChange={() => toggleMenu(menu)}
              />
            </div>
            {hasChildren && expanded && (
              <div className="permission-tree__group" role="group">
                {menu.items.map((permission) => (
                  <div key={permission.id} className="permission-tree__child" role="treeitem">
                    <TreeCheckbox
                      checked={selectedIds.has(permission.id)}
                      label={permission.text}
                      onChange={() => togglePermission(menu, permission.id)}
                    />
                  </div>
                ))}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
});

PermissionTreeView.displayName = 'PermissionTreeView';
