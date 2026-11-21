import { FeatureHubLayout } from '@nubitio/admin';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { IN_DIALOG, settingsTabs } from './settingsTabs';
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
      defaultPath="/config/series"
      tabsAriaLabel="Secciones de ajustes"
      className="settings-hub"
      // These live in the account settings dialog, not in this hub.
      tabs={settingsTabs({ showAdminTabs }).filter((tab) => !IN_DIALOG.has(tab.key))}
    />
  );
};
