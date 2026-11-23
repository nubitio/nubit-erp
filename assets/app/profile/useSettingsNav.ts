import { settingsTabs } from '@features/settings/settingsTabs';
import { hasPermission } from '@shared/permissions';
import { buildProfileNav } from './profileNav';
import type { SettingsNavGroup } from './settings/SettingsLayout';

/** What the settings list offers the signed-in user; the dialog and the search share it. */
export function useSettingsNav(): SettingsNavGroup[] {
  return buildProfileNav({
    organizationTabs: settingsTabs(),
    canSeeOrganization: hasPermission('settings.view'),
    canManagePlan: hasPermission('settings.edit'),
  });
}
