import { SunatSettingsSection } from './components/TenantConfigSettings';
import { SunatCertificateCard } from './components/SunatCertificateCard';
import { SettingsPageHeader } from './components/SettingsPageHeader';
import './settingsHub.scss';

export const SettingsSunatTab = () => (
  <div className="settings-panel settings-sunat" data-testid="settings-sunat-tab">
    <SettingsPageHeader
      title="Conexión con SUNAT"
      description="Certificado digital, credenciales SOL y reglas de envío."
    />
    <SunatCertificateCard />
    <SunatSettingsSection />
  </div>
);
