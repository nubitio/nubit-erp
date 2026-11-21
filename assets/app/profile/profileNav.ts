import type { SettingsTab } from '@features/settings/settingsTabs';
import type { SettingsNavGroup } from './settings/SettingsLayout';

export const PROFILE_SECTIONS = ['account', 'security', 'appearance', 'usage', 'plan'] as const;
export type ProfileSection = (typeof PROFILE_SECTIONS)[number];

/** The `?settings=<section>` of the URL; anything unknown (or absent) is the account page. */
export function parseSection(param: string | undefined): ProfileSection {
  return PROFILE_SECTIONS.find((section) => section === param) ?? 'account';
}

interface NavInput {
  /** The tenant's settings pages, already filtered by what this user may open. */
  organizationTabs: SettingsTab[];
  /** May see the tenant's settings pages at all. */
  canSeeOrganization: boolean;
  /** May see plan, usage and billing. */
  canManagePlan: boolean;
}

/**
 * What the account settings list on the left. "Cuenta" is for everyone; the tenant's own settings
 * and its plan are for whoever administers it, so a cashier never sees them.
 */
export function buildProfileNav({
  organizationTabs,
  canSeeOrganization,
  canManagePlan,
}: NavInput): SettingsNavGroup[] {
  const groups: SettingsNavGroup[] = [
    {
      key: 'account',
      title: 'Cuenta',
      items: [
        { key: 'account', label: 'Perfil', icon: 'ph-user' },
        {
          key: 'security',
          label: 'Seguridad',
          icon: 'ph-shield-check',
        },
        {
          key: 'appearance',
          label: 'Apariencia',
          icon: 'ph-palette',
        },
      ],
    },
  ];

  if (canManagePlan) {
    groups.push({
      key: 'plan',
      title: 'Plan',
      items: [
        { key: 'usage', label: 'Uso', icon: 'ph-gauge' },
        {
          key: 'plan',
          label: 'Plan y facturación',
          icon: 'ph-credit-card',
        },
      ],
    });
  }

  const visibleTabs = organizationTabs.filter((tab) => tab.visible);
  if (canSeeOrganization && visibleTabs.length > 0) {
    groups.push({
      key: 'organization',
      title: 'Organización',
      items: visibleTabs.map((tab) => ({
        key: `org-${tab.key}`,
        label: tab.label,
        icon: tab.icon,
        href: tab.path,
      })),
    });
  }

  return groups;
}
