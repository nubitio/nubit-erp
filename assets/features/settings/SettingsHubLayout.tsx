import { FeatureHubLayout } from '@nubitio/admin';
import { useTenantRuntimeConfig } from '@shared/hooks';
import './settingsHub.scss';

export const SettingsHubLayout = () => {
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const navigationTier = runtimeConfig?.profile.navigationTier ?? 'basic';
  const showAdminTabs = navigationTier === 'standard' || navigationTier === 'advanced';

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
        { key: 'users', label: 'Usuarios', path: '/config/users', icon: 'ph-users', visible: showAdminTabs },
        { key: 'branches', label: 'Sucursales', path: '/config/branches', icon: 'ph-map-pin', visible: showAdminTabs },
        {
          key: 'general',
          label: 'General',
          path: '/config/general',
          icon: 'ph-sliders-horizontal',
          visible: showAdminTabs,
        },
      ]}
    />
  );
};
