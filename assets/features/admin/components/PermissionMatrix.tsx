import './PermissionMatrix.scss';
import { forwardRef, useImperativeHandle, useMemo, useState } from 'react';

export type PermissionModule = {
  key: string;
  label: string;
  permissions: { key: string; label: string; granted: boolean }[];
};

export interface PermissionMatrixHandle {
  setModules: (modules: PermissionModule[]) => void;
  getGrantedKeys: () => string[];
}

type MatrixCheckboxProps = {
  checked: boolean;
  indeterminate?: boolean;
  label: string;
  onChange: () => void;
};

function MatrixCheckbox({ checked, indeterminate = false, label, onChange }: MatrixCheckboxProps) {
  return (
    <label className="permission-matrix__checkbox">
      <input
        type="checkbox"
        checked={checked}
        className="permission-matrix__input"
        ref={(element) => {
          if (element) element.indeterminate = indeterminate;
        }}
        onChange={onChange}
      />
      <span>{label}</span>
    </label>
  );
}

export const PermissionMatrix = forwardRef<PermissionMatrixHandle>((_props, ref) => {
  const [modules, setModulesState] = useState<PermissionModule[]>([]);
  const [grantedKeys, setGrantedKeys] = useState<Set<string>>(() => new Set());
  const [query, setQuery] = useState('');

  useImperativeHandle(ref, () => ({
    setModules: (nextModules) => {
      setModulesState(nextModules);
      setQuery('');
      setGrantedKeys(
        new Set(
          nextModules.flatMap((module) =>
            module.permissions
              .filter((permission) => permission.granted)
              .map((permission) => permission.key),
          ),
        ),
      );
    },
    getGrantedKeys: () => [...grantedKeys],
  }));

  const visibleModules = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) return modules;

    return modules
      .map((module) => {
        if (module.label.toLowerCase().includes(normalized)) return module;

        return {
          ...module,
          permissions: module.permissions.filter((permission) =>
            permission.label.toLowerCase().includes(normalized),
          ),
        };
      })
      .filter((module) => module.permissions.length > 0);
  }, [modules, query]);

  const togglePermission = (permissionKey: string) => {
    const next = new Set(grantedKeys);
    if (next.has(permissionKey)) {
      next.delete(permissionKey);
    } else {
      next.add(permissionKey);
    }
    setGrantedKeys(next);
  };

  const toggleModule = (module: PermissionModule) => {
    const allGranted = module.permissions.every((permission) => grantedKeys.has(permission.key));
    const next = new Set(grantedKeys);

    module.permissions.forEach((permission) => {
      if (allGranted) {
        next.delete(permission.key);
      } else {
        next.add(permission.key);
      }
    });

    setGrantedKeys(next);
  };

  const totalCount = modules.reduce((count, module) => count + module.permissions.length, 0);
  const grantedCount = grantedKeys.size;

  return (
    <div className="permission-matrix">
      <div className="permission-matrix__header">
        <input
          type="search"
          className="permission-matrix__search"
          placeholder="Buscar permiso…"
          aria-label="Buscar permiso"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
        />
        <span className="permission-matrix__count">
          {grantedCount} de {totalCount} permisos
        </span>
      </div>
      <div className="permission-matrix__modules">
        {visibleModules.map((module) => {
          const grantedInModule = module.permissions.filter((permission) =>
            grantedKeys.has(permission.key),
          ).length;
          const allGranted =
            module.permissions.length > 0 && grantedInModule === module.permissions.length;
          const someGranted = grantedInModule > 0 && !allGranted;

          return (
            <section key={module.key} className="permission-matrix__module">
              <header className="permission-matrix__module-header">
                <MatrixCheckbox
                  checked={allGranted}
                  indeterminate={someGranted}
                  label={module.label}
                  onChange={() => toggleModule(module)}
                />
                <span className="permission-matrix__module-count">
                  {grantedInModule}/{module.permissions.length}
                </span>
              </header>
              <div className="permission-matrix__permissions">
                {module.permissions.map((permission) => (
                  <MatrixCheckbox
                    key={permission.key}
                    checked={grantedKeys.has(permission.key)}
                    label={permission.label}
                    onChange={() => togglePermission(permission.key)}
                  />
                ))}
              </div>
            </section>
          );
        })}
        {visibleModules.length === 0 && (
          <p className="permission-matrix__empty">Sin permisos que coincidan con la búsqueda.</p>
        )}
      </div>
    </div>
  );
});

PermissionMatrix.displayName = 'PermissionMatrix';
