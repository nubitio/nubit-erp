import { AppDialog, Alert, Skeleton } from '@nubitio/ui';
import { parseSection, type ProfileSection } from './profileNav';
import { SettingsLayout } from './settings/SettingsLayout';
import { AccountSection } from './sections/AccountSection';
import { AppearanceSection } from './sections/AppearanceSection';
import { BranchesSection } from './sections/BranchesSection';
import { CompanySection } from './sections/CompanySection';
import { IntegrationsSection } from './sections/IntegrationsSection';
import { OperationSection } from './sections/OperationSection';
import { PlanSection } from './sections/PlanSection';
import { SecuritySection } from './sections/SecuritySection';
import { SeriesSection } from './sections/SeriesSection';
import { SunatSection } from './sections/SunatSection';
import { UsageSection } from './sections/UsageSection';
import { useProfile } from './useProfile';
import { useSettingsDialog } from './useSettingsDialog';
import { useSettingsNav } from './useSettingsNav';

const TITLES: Record<ProfileSection, string> = {
  account: 'Perfil',
  security: 'Seguridad',
  appearance: 'Apariencia',
  usage: 'Uso',
  plan: 'Plan y facturación',
  company: 'Empresa',
  sunat: 'SUNAT',
  general: 'Operación',
  series: 'Series',
  branches: 'Sucursales',
  integrations: 'Integraciones',
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
  const groups = useSettingsNav();

  // Only what the list offers this user opens; anyone else who types the address sees the profile.
  const offered = groups.some((group) => group.items.some((item) => item.key === requested));
  const section: ProfileSection = offered ? requested : 'account';

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
        {section === 'sunat' && <SunatSection />}
        {section === 'general' && <OperationSection />}
        {section === 'series' && <SeriesSection />}
        {section === 'branches' && <BranchesSection />}
        {section === 'integrations' && <IntegrationsSection />}
      </SettingsLayout>
    </AppDialog>
  );
};
