import { describe, expect, it } from 'vitest';
import type { SettingsTab } from '@features/settings/settingsTabs';
import { buildProfileNav, parseSection } from './profileNav';

const tab = (key: string, visible = true): SettingsTab => ({
  key,
  label: key,
  path: `/config/${key}`,
  icon: 'ph-gear',
  visible,
});

const keys = (nav: ReturnType<typeof buildProfileNav>) => nav.map((group) => group.key);

describe('buildProfileNav', () => {
  it('gives a cashier only the account pages', () => {
    const nav = buildProfileNav({
      organizationTabs: [tab('company')],
      canSeeOrganization: false,
      canManagePlan: false,
    });
    expect(keys(nav)).toEqual(['account']);
    expect(nav[0]?.items.map((item) => item.key)).toEqual(['account', 'security', 'appearance']);
  });

  it('adds plan and organization for the tenant admin', () => {
    const nav = buildProfileNav({
      organizationTabs: [tab('company'), tab('users')],
      canSeeOrganization: true,
      canManagePlan: true,
    });
    expect(keys(nav)).toEqual(['account', 'plan', 'organization']);
    expect(nav[1]?.items.map((item) => item.key)).toEqual(['usage', 'plan']);
  });

  it('links to the tenant settings pages, leaving the panel, and drops the hidden ones', () => {
    const organization = buildProfileNav({
      organizationTabs: [tab('company'), tab('roles', false)],
      canSeeOrganization: true,
      canManagePlan: false,
    }).find((group) => group.key === 'organization');
    expect(organization?.items).toEqual([
      {
        key: 'org-company',
        label: 'company',
        icon: 'ph-gear',
        href: '/config/company',
      },
    ]);
  });

  it('omits the organization group when nothing in it is visible', () => {
    const nav = buildProfileNav({
      organizationTabs: [tab('roles', false)],
      canSeeOrganization: true,
      canManagePlan: false,
    });
    expect(keys(nav)).toEqual(['account']);
  });
});

describe('parseSection', () => {
  it('reads a known section and falls back to the account page', () => {
    expect(parseSection('security')).toBe('security');
    expect(parseSection('usage')).toBe('usage');
    expect(parseSection(undefined)).toBe('account');
    expect(parseSection('nope')).toBe('account');
  });
});
