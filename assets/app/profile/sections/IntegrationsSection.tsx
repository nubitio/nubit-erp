import { hasPermission } from '@shared/permissions';
import { ApiKeysGroup } from './integrations/ApiKeysGroup';
import { WebhooksGroup } from './integrations/WebhooksGroup';

/** "Integraciones": API keys and webhooks to connect external systems. */
export const IntegrationsSection = () => {
  const canEdit = hasPermission('settings.edit');
  return (
    <>
      <ApiKeysGroup canEdit={canEdit} />
      <WebhooksGroup canEdit={canEdit} />
    </>
  );
};
