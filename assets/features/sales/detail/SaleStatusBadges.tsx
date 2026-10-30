import { Badge } from '@nubitio/ui';
import { sunatStatus } from '../sunat/sunatStatus';
import { paymentStateLabels, sunatStateLabels } from './saleDetailModel';

export function SaleStatusBadges({
  paymentState,
  sunatState,
  sunatCode,
  hasInvoice,
}: {
  paymentState?: string;
  sunatState?: string;
  sunatCode?: string | null;
  hasInvoice?: boolean;
}) {
  const fiscal = hasInvoice && sunatState !== 'not_applicable' ? sunatStatus(sunatCode) : null;
  return (
    <>
      {paymentState && (
        <Badge
          size="sm"
          pill
          variant={
            paymentState === 'paid' ? 'success' : paymentState === 'voided' ? 'danger' : 'warning'
          }
        >
          {paymentStateLabels[paymentState] ?? 'Estado de pago no disponible'}
        </Badge>
      )}
      {sunatState && (
        <Badge
          size="sm"
          pill
          variant={
            fiscal?.stage === 'rejected'
              ? 'danger'
              : fiscal?.stage === 'accepted' || sunatState === 'accepted'
                ? 'success'
                : sunatState === 'pending'
                  ? 'warning'
                  : 'secondary'
          }
        >
          {fiscal?.label ?? sunatStateLabels[sunatState] ?? 'Estado SUNAT no disponible'}
        </Badge>
      )}
    </>
  );
}
