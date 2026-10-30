import type { CardViewContext } from '@nubitio/crud';
import { SaleStatusBadges } from './SaleStatusBadges';
import { saleDate, saleDocument, saleMoney, type SaleDetail } from './saleDetailModel';

export function SaleCardContent({ row }: CardViewContext) {
  const sale = row as unknown as SaleDetail;
  const methods = [
    ...new Set(
      [
        ...(sale.payments ?? []).map((payment) => payment.method_name),
        ...(sale.paymentState === 'due' ? ['Crédito'] : []),
      ].filter(Boolean),
    ),
  ];
  return (
    <span className="sale-summary-card">
      <span className="sale-summary-card__heading">
        <strong className="sale-summary-card__customer">
          {sale.customer?.businessName || 'Cliente no registrado'}
        </strong>
        <strong className="sale-summary-card__total">
          {saleMoney(sale.grandTotal, sale.currency)}
        </strong>
      </span>
      <span className="sale-summary-card__meta">
        {saleDocument(sale)} · {saleDate(sale.issuedDate ?? sale.issuedAt)}
        {methods.length
          ? ` · ${methods.join(' + ')}`
          : sale.paymentMethod?.name
            ? ` · ${sale.paymentMethod.name}`
            : ''}
      </span>
      <span className="sale-summary-card__badges">
        <SaleStatusBadges
          paymentState={sale.paymentState}
          sunatState={sale.sunatState}
          sunatCode={sale.sunatStatus}
          hasInvoice={sale.hasInvoice}
        />
      </span>
    </span>
  );
}
