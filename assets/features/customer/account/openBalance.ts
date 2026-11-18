import type { ReceivableInvoice } from '../../sales/services/receivableService';

/**
 * Total owed across a customer's open invoices, as a decimal string. Summed in integer cents so
 * floating point never shows up in a balance. The currency is the first invoice's: the receivables
 * screens assume one currency per customer, and so does the drawer this feeds.
 */
export function openBalance(invoices: ReceivableInvoice[]): { total: string; currency: string } {
  const cents = invoices.reduce((sum, invoice) => {
    const value = Number.parseFloat(invoice.balanceDue);
    return sum + (Number.isFinite(value) ? Math.round(value * 100) : 0);
  }, 0);

  return { total: (cents / 100).toFixed(2), currency: invoices[0]?.currency ?? 'PEN' };
}
