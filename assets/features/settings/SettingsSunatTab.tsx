import { SunatSettingsSection } from './components/TenantConfigSettings';
import { SunatCertificateCard } from './components/SunatCertificateCard';
import './settingsHub.scss';

export const SettingsSunatTab = () => (
  <div className="settings-panel settings-sunat" data-testid="settings-sunat-tab">
    <SunatCertificateCard />
    <SunatSettingsSection />
  </div>
);
