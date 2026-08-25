import './RolePermissionsPanel.scss';
import { useState, type RefObject } from 'react';
import { PermissionTreeView, type PermissionTreeHandle } from './PermissionTreeView';
import { PermissionMatrix, type PermissionMatrixHandle } from './PermissionMatrix';

type TabId = 'menus' | 'actions';

type RolePermissionsPanelProps = {
  treeRef: RefObject<PermissionTreeHandle | null>;
  matrixRef: RefObject<PermissionMatrixHandle | null>;
};

const TABS: { id: TabId; label: string; icon: string }[] = [
  { id: 'menus', label: 'Menús', icon: 'ph-list' },
  { id: 'actions', label: 'Acciones', icon: 'ph-shield-check' },
];

/**
 * Content of the "Permisos" dialog on the Roles page: menu visibility
 * (tree) and granular action permissions (matrix). Both panels stay
 * mounted so their imperative handles survive tab switches.
 */
export const RolePermissionsPanel = ({ treeRef, matrixRef }: RolePermissionsPanelProps) => {
  const [tab, setTab] = useState<TabId>('menus');

  return (
    <div className="role-permissions-panel">
      <div className="role-permissions-panel__tabs" role="tablist">
        {TABS.map((item) => (
          <button
            key={item.id}
            type="button"
            role="tab"
            aria-selected={tab === item.id}
            className={`role-permissions-panel__tab${tab === item.id ? ' is-active' : ''}`}
            onClick={() => setTab(item.id)}
          >
            <i className={`ph ${item.icon}`} aria-hidden="true" />
            {item.label}
          </button>
        ))}
      </div>
      <div hidden={tab !== 'menus'}>
        <PermissionTreeView ref={treeRef} />
      </div>
      <div hidden={tab !== 'actions'}>
        <p className="role-permissions-panel__help">
          Los permisos de acción controlan lo que el usuario puede hacer aunque tenga visible el menú.
          Para cambiar solo el precio de una venta usa “Modificar precio de línea en el POS”; para
          cambiar el precio maestro usa “Editar productos y precios del catálogo”.
        </p>
        <PermissionMatrix ref={matrixRef} />
      </div>
    </div>
  );
};
