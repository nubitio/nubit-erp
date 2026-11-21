import { AppDialog, Alert, Skeleton } from '@nubitio/ui';
import { settingsTabs } from '@features/settings/settingsTabs';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { hasPermission } from '@shared/permissions';
import { buildProfileNav, parseSection, type ProfileSection } from './profileNav';
import { SettingsLayout } from './settings/SettingsLayout';
import { AccountSection } from './sections/AccountSection';
import { AppearanceSection } from './sections/AppearanceSection';
import { CompanySection } from './sections/CompanySection';
import { PlanSection } from './sections/PlanSection';
import { SecuritySection } from './sections/SecuritySection';
import { UsageSection } from './sections/UsageSection';
import { useProfile } from './useProfile';
import { useSettingsDialog } from './useSettingsDialog';

const TITLES: Record<ProfileSection, string> = {
  account: 'Perfil',
  security: 'Seguridad',
  appearance: 'Apariencia',
  usage: 'Uso',
  plan: 'Plan y facturación',
  company: 'Empresa',
};

/**
 * "Ajustes": the signed-in user's profile, security and appearance, plus plan and organization for
 * whoever administers the tenant. A dialog over the current page, driven by `?settings=<section>`.
 */
export const SettingsDialog = () => {
  const settings = useSettingsDialog();
  if (!settings.isOpen) return null;
  return (
    <OpenSettings section={settings.section} onSelect={settings.select} onClose={settings.close} />
  );
};

const OpenSettings = ({
  section: requested,
  onSelect,
  onClose,
}: {
  section: ProfileSection;
  onSelect: (section: ProfileSection) => void;
  onClose: () => void;
}) => {
  const { data: profile, isError, isPending } = useProfile();
  const { data: runtimeConfig } = useTenantRuntimeConfig();

  const navigationTier = runtimeConfig?.profile.navigationTier ?? 'basic';
  const canManagePlan = hasPermission('settings.edit');
  const canSeeCompany = hasPermission('settings.view');
  const groups = buildProfileNav({
    organizationTabs: settingsTabs({
      showAdminTabs: navigationTier === 'standard' || navigationTier === 'advanced',
    }),
    canSeeOrganization: canSeeCompany,
    canManagePlan,
  });

  // Plan, usage and the company are the tenant admin's; anyone else who types the address sees the profile.
  const section: ProfileSection =
    ((requested === 'usage' || requested === 'plan') && !canManagePlan) ||
    (requested === 'company' && !canSeeCompany)
      ? 'account'
      : requested;

  return (
    <AppDialog
      open
      title="Ajustes"
      onClose={onClose}
      width="min(1080px, 94vw)"
      height="min(760px, 90vh)"
      flush
    >
      <SettingsLayout
        title={TITLES[section]}
        groups={groups}
        activeKey={section}
        onSelect={(key) => onSelect(parseSection(key))}
      >
        {section === 'account' && isError && (
          <Alert tone="danger">No se pudo cargar tu perfil. Recarga la página.</Alert>
        )}
        {section === 'account' && isPending && <Skeleton variant="rect" height={240} />}
        {profile && section === 'account' && <AccountSection profile={profile} />}
        {section === 'security' && <SecuritySection />}
        {section === 'appearance' && <AppearanceSection />}
        {section === 'usage' && <UsageSection />}
        {section === 'plan' && <PlanSection />}
        {section === 'company' && <CompanySection />}
      </SettingsLayout>
    </AppDialog>
  );
};
