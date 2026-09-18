import {
  PosBehaviorSettings,
  ReceiptSettings,
  TenantBehaviorSettings,
} from './components/TenantConfigSettings';
import { SettingsPageHeader } from './components/SettingsPageHeader';
import './settingsHub.scss';

export const SettingsGeneralTab = () => (
  <div className="settings-panel settings-operation" data-testid="settings-general-tab">
    <SettingsPageHeader
      title="Cómo funciona tu negocio"
      description="Comportamiento del punto de venta, impresión, impuestos e inventario."
    />
    <PosBehaviorSettings />
    <ReceiptSettings />
    <TenantBehaviorSettings />
  </div>
);
