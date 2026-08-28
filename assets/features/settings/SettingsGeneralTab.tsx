import { PosBehaviorSettings, TenantBehaviorSettings } from './components/TenantConfigSettings';
import './settingsHub.scss';

export const SettingsGeneralTab = () => (
  <div className="settings-panel config-page" data-testid="settings-general-tab">
    <PosBehaviorSettings />
    <TenantBehaviorSettings />
  </div>
);
