export interface SettingsTab {
  key: string;
  label: string;
  path: string;
  /** Phosphor icon class (`ph-…`); render it next to the base `ph` class. */
  icon: string;
  visible: boolean;
}

/**
 * The tenant's settings pages, each edited inside the account settings dialog ("Organización").
 * Users, roles, invitations and activity are lists of records, so they are pages of their own
 * under the "Administración" menu group instead.
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
