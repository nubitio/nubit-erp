import { IntegrationApiKeysCard } from './components/IntegrationApiKeysCard';
import { IntegrationWebhooksCard } from './components/IntegrationWebhooksCard';
import { SettingsPageHeader } from './components/SettingsPageHeader';
import './settingsHub.scss';

export const SettingsIntegrationsTab = () => (
  <div className="settings-panel settings-integrations" data-testid="settings-integrations-tab">
    <SettingsPageHeader
      title="Integraciones"
      description="Claves de API y webhooks para conectar sistemas externos."
    />
    <IntegrationApiKeysCard />
    <IntegrationWebhooksCard />
  </div>
);
