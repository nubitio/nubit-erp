import { describe, expect, it } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';
import { SharedUtils } from './SharedUtils';

const label = (sale: Parameters<typeof SharedUtils.saleStatusFormatter>[0]) =>
  renderToStaticMarkup(SharedUtils.saleStatusFormatter(sale) ?? <></>);

describe('billStatusFormatter', () => {
  const bill = (value: Parameters<typeof SharedUtils.billStatusFormatter>[0]) =>
    renderToStaticMarkup(SharedUtils.billStatusFormatter(value) ?? <></>);

  it('labels SUNAT code 0 as accepted, not merely sent', () => {
    expect(bill(0)).toContain('ACEPTADO');
    expect(bill('aceptado')).toContain('ACEPTADO');
  });

  it('labels missing CDR as pending SUNAT', () => {
    expect(bill(null)).toContain('PENDIENTE SUNAT');
    expect(bill('pendiente')).toContain('PENDIENTE SUNAT');
  });
});

describe('saleStatusFormatter', () => {
  it('labels a completed sale without invoice as cobrada', () => {
    expect(label({ status: 'COMPLETED' })).toContain('COBRADA');
  });

  it('labels an invoiced sale waiting for SUNAT as emitida', () => {
    expect(label({ invoiceFullNumber: 'F001-1037', sunatStatus: null })).toContain('EMITIDA');
  });

  it('labels a SUNAT-accepted invoice clearly', () => {
    expect(label({ invoiceFullNumber: 'F001-1037', sunatStatus: '0' })).toContain('ACEPTADA SUNAT');
  });

  it('labels a voided sale as anulado', () => {
    expect(label({ status: 'VOIDED', invoiceFullNumber: 'F001-1' })).toContain('ANULADO');
  });
});
