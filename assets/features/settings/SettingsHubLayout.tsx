import { FeatureHubLayout } from '@nubitio/admin';
import { useTenantRuntimeConfig } from '@shared/hooks';
import './settingsHub.scss';

export const SettingsHubLayout = () => {
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const showWarehouses = runtimeConfig?.ui.inventory.showWarehouseManagement ?? false;

  return (
    <FeatureHubLayout
      density="compact"
      title="Configuración"
      basePath="/config"
      defaultPath="/config/company"
      tabsAriaLabel="Secciones de configuración"
      className="settings-hub"
      tabs={[
        { key: 'company', label: 'Empresa', path: '/config/company', icon: 'ph-buildings', visible: true },
        { key: 'sunat', label: 'SUNAT', path: '/config/sunat', icon: 'ph-seal-check', visible: true },
        { key: 'series', label: 'Series', path: '/config/series', icon: 'ph-hash', visible: true },
        { key: 'users', label: 'Usuarios', path: '/config/users', icon: 'ph-users', visible: true },
        { key: 'branches', label: 'Sucursales', path: '/config/branches', icon: 'ph-map-pin', visible: true },
        {
          key: 'warehouses',
          label: 'Almacenes',
          path: '/config/warehouses',
          icon: 'ph-warehouse',
          visible: showWarehouses,
        },
        { key: 'general', label: 'General', path: '/config/general', icon: 'ph-sliders-horizontal', visible: true },
      ]}
    />
  );
};