import { FeatureHubLayout } from '@nubitio/admin';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { settingsTabs } from './settingsTabs';
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
      defaultPath="/config/sunat"
      tabsAriaLabel="Secciones de ajustes"
      className="settings-hub"
      // The company lives in the account settings dialog, not in this hub.
      tabs={settingsTabs({ showAdminTabs }).filter((tab) => tab.key !== 'company')}
    />
  );
};
