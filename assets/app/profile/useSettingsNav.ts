import { settingsTabs } from '@features/settings/settingsTabs';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { hasPermission } from '@shared/permissions';
import { buildProfileNav } from './profileNav';
import type { SettingsNavGroup } from './settings/SettingsLayout';

/** What the settings list offers the signed-in user; the dialog and the search share it. */
export function useSettingsNav(): SettingsNavGroup[] {
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const navigationTier = runtimeConfig?.profile.navigationTier ?? 'basic';
  return buildProfileNav({
    organizationTabs: settingsTabs({
      showAdminTabs: navigationTier === 'standard' || navigationTier === 'advanced',
    }),
    canSeeOrganization: hasPermission('settings.view'),
    canManagePlan: hasPermission('settings.edit'),
  });
}
