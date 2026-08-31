import { IntegrationApiKeysCard } from './components/IntegrationApiKeysCard';
import { IntegrationWebhooksCard } from './components/IntegrationWebhooksCard';
import './settingsHub.scss';

export const SettingsIntegrationsTab = () => (
  <div className="settings-panel settings-integrations" data-testid="settings-integrations-tab">
    <IntegrationApiKeysCard />
    <IntegrationWebhooksCard />
  </div>
);
