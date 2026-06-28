import { SunatSettingsSection } from './components/TenantConfigSettings';
import './settingsHub.scss';

export const SettingsSunatTab = () => (
  <div className="settings-panel" data-testid="settings-sunat-tab">
    <SunatSettingsSection />
  </div>
);