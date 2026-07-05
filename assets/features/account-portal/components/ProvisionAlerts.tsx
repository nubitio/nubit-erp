import { Button } from '@nubitio/ui';
import type { ProvisionRecord } from '../api/accountPortalClient';

interface ProvisionAlertsProps {
  active: ProvisionRecord[];
  failed: ProvisionRecord[];
  onDismiss: (id: number) => void;
}

export function ProvisionAlerts({ active, failed, onDismiss }: ProvisionAlertsProps) {
  if (active.length === 0 && failed.length === 0) return null;

  return (
    <div className="ap-alerts">
      {active.map((item) => (
        <div key={item.id} className="ap-alert ap-alert--info">
          <div className="ap-alert__spinner" aria-hidden="true" />
          <div className="ap-alert__body">
            <strong>{item.tenant_name}</strong>
            <span>Creando tu empresa — esto puede tomar unos minutos.</span>
          </div>
        </div>
      ))}

      {failed.map((item) => (
        <div key={item.id} className="ap-alert ap-alert--error">
          <i className="ph ph-warning-circle ap-alert__icon" aria-hidden="true" />
          <div className="ap-alert__body">
            <strong>{item.tenant_name}</strong>
            <span>Hubo un problema creando tu empresa. Contacta a soporte.</span>
          </div>
          <Button type="button" variant="ghost" size="sm" onClick={() => onDismiss(item.id)} aria-label="Descartar">
            <i className="ph ph-x" aria-hidden="true" />
          </Button>
        </div>
      ))}
    </div>
  );
}