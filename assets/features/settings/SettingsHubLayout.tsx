import { Navigate, useLocation } from 'react-router-dom';
import { FeatureHubLayout } from '@nubitio/admin';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { IN_DIALOG, LINK_ONLY, settingsTabs } from './settingsTabs';
import './settingsHub.scss';

/** The administration screens that are lists at scale; the rest of the settings are in the dialog. */
export const SettingsHubLayout = () => {
  const { pathname } = useLocation();
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const navigationTier = runtimeConfig?.profile.navigationTier ?? 'basic';
  const showAdminTabs = navigationTier === 'standard' || navigationTier === 'advanced';
  const tabs = settingsTabs({ showAdminTabs }).filter(
    (tab) => tab.visible && !IN_DIALOG.has(tab.key) && !LINK_ONLY.has(tab.key),
  );

  // A tenant without the administration screens has nothing to show here: open the dialog on the
  // page that was asked for (/config/series -> Series), or on the company.
  if (tabs.length === 0) {
    const asked = pathname.split('/')[2] ?? '';
    const section = IN_DIALOG.has(asked) ? asked : 'company';
    return <Navigate to={`/dashboard?settings=${section}`} replace />;
  }

  return (
    <FeatureHubLayout
      density="compact"
      title="Administración"
      basePath="/config"
      defaultPath={tabs[0].path}
      tabsAriaLabel="Secciones de ajustes"
      className="settings-hub"
      tabs={tabs}
    />
  );
};
