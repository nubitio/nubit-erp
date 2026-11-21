import { Alert, Button, Skeleton } from '@nubitio/ui';
import { useEntitlements } from '@shared/hooks/useEntitlements';
import { SettingsGroup, SettingsRow } from '../settings/SettingsRow';
import { planLabel } from '../usageFormat';

/**
 * Plan and billing. The plan is real (from the entitlements); changing it and paying will live in
 * Nubit Billing, so those controls are in place but off until the ERP is connected to it.
 */
export const PlanSection = () => {
  const { data, isPending, isError } = useEntitlements();

  if (isError) return <Alert tone="warning">No se pudo cargar tu plan.</Alert>;
  if (isPending) return <Skeleton variant="rect" height={160} />;

  return (
    <>
      <SettingsGroup>
        <SettingsRow label="Plan actual" description="Lo que incluye tu suscripción.">
          <strong>{planLabel(data.plan)}</strong>
        </SettingsRow>
        <SettingsRow
          label="Cambiar de plan"
          description="Pronto podrás cambiarlo desde aquí, con la facturación de Nubit Billing."
        >
          <Button type="button" variant="secondary" size="sm" disabled>
            Cambiar de plan
          </Button>
        </SettingsRow>
        <SettingsRow
          label="Pagos y comprobantes"
          description="Método de pago e historial de cobros de tu suscripción."
        >
          <Button type="button" variant="secondary" size="sm" disabled>
            Ver
          </Button>
        </SettingsRow>
      </SettingsGroup>
    </>
  );
};
