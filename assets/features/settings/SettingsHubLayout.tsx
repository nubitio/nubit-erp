import { FeatureHubLayout } from '@nubitio/admin';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { hasPermission } from '@shared/permissions';
import './settingsHub.scss';

export const SettingsHubLayout = () => {
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const navigationTier = runtimeConfig?.profile.navigationTier ?? 'basic';
  const showAdminTabs = navigationTier === 'standard' || navigationTier === 'advanced';

  return (
    <FeatureHubLayout
      density="compact"
      title="Ajustes"
      basePath="/config"
      defaultPath="/config/company"
      tabsAriaLabel="Secciones de ajustes"
      className="settings-hub"
      tabs={[
        {
          key: 'company',
          label: 'Empresa',
          path: '/config/company',
          icon: 'ph-buildings',
          visible: true,
        },
        {
          key: 'sunat',
          label: 'SUNAT',
          path: '/config/sunat',
          icon: 'ph-seal-check',
          visible: true,
        },
        { key: 'series', label: 'Series', path: '/config/series', icon: 'ph-hash', visible: true },
        {
          key: 'users',
          label: 'Usuarios',
          path: '/config/users',
          icon: 'ph-users',
          visible: showAdminTabs,
        },
        {
          key: 'roles',
          label: 'Roles',
          path: '/config/roles',
          icon: 'ph-user-gear',
          visible: showAdminTabs,
        },
        {
          key: 'activity',
          label: 'Actividad',
          path: '/config/activity',
          icon: 'ph-clock-counter-clockwise',
          visible: showAdminTabs && hasPermission('activity.view'),
        },
        {
          key: 'branches',
          label: 'Sucursales',
          path: '/config/branches',
          icon: 'ph-map-pin',
          visible: showAdminTabs,
        },
        {
          key: 'integrations',
          label: 'Integraciones',
          path: '/config/integrations',
          icon: 'ph-plugs-connected',
          visible: showAdminTabs,
        },
        {
          key: 'general',
          label: 'Operación',
          path: '/config/general',
          icon: 'ph-sliders-horizontal',
          // Always reachable: it hosts POS behaviour toggles (auto-invoice,
          // auto-print) that a single-store tenant must be able to change.
          visible: true,
        },
      ]}
    />
  );
};
