import { hasPermission } from '@shared/permissions';

/** The pages edited inside the account settings dialog; the rest are screens of their own. */
export const IN_DIALOG: ReadonlySet<string> = new Set(['company', 'sunat', 'general']);

export interface SettingsTab {
  key: string;
  label: string;
  path: string;
  /** Phosphor icon class (`ph-…`); render it next to the base `ph` class. */
  icon: string;
  visible: boolean;
}

/**
 * The tenant's settings pages and who gets to see them. One list for the settings hub's tabs and
 * for the "Organización" group of the account settings, so the two never disagree.
 *
 * `showAdminTabs`: the tenant's navigation tier includes the administration screens.
 */
export function settingsTabs({ showAdminTabs }: { showAdminTabs: boolean }): SettingsTab[] {
  return [
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
      key: 'invitations',
      label: 'Invitaciones',
      path: '/config/invitations',
      icon: 'ph-envelope-simple',
      visible: showAdminTabs && Boolean(window.app_identity) && hasPermission('users.view'),
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
  ];
}
