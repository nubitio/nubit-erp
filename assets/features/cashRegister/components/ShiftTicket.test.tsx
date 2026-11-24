import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import type { ShiftReportData } from '../services/cashSessionService';
import { ShiftTicket } from './ShiftTicket';

const base: ShiftReportData = {
  company: { name: 'Comercial Andina S.A.C.', ruc: '20512345678', branch: 'Sucursal Principal' },
  shift: {
    id: 481,
    status: 'open',
    registerName: 'Caja 01',
    cashier: 'rosa',
    closedBy: null,
    openedAt: '2026-11-23T08:02:00-05:00',
    closedAt: null,
  },
  documents: {
    rows: [
      {
        type: '03',
        label: 'Boletas',
        count: 74,
        ranges: ['B001-00004101 a 00004174'],
        taxable: '3356.27',
        igv: '606.13',
        total: '3962.40',
      },
      {
        type: '07',
        label: 'N. de crédito',
        count: 1,
        ranges: ['BC01-00000007'],
        taxable: '-84.75',
        igv: '-15.25',
        total: '-100.00',
      },
    ],
    count: 75,
    taxable: '3271.52',
    igv: '590.88',
    total: '3862.40',
  },
  sales: {
    total: '3862.40',
    byMethod: [{ name: 'Efectivo', operations: 31, amount: '2310.40', percent: '59.8' }],
    credit: { amount: '0.00', operations: 0, percent: '0.0' },
  },
  cash: {
    opening: '200.00',
    cashSales: '2310.40',
    income: '150.00',
    expenses: '85.10',
    withdrawals: '730.00',
    expected: '1845.30',
    counted: null,
    difference: null,
    denominations: null,
    fundKept: null,
    withdrawnAtClose: null,
    note: null,
  },
  outflows: [],
};

afterEach(cleanup);

describe('ShiftTicket', () => {
  it('prints the X cut of an open shift as informative, without ranges or signatures', () => {
    render(<ShiftTicket report={base} />);

    expect(screen.getByText('REPORTE X')).toBeTruthy();
    expect(screen.getByText('CORTE PARCIAL')).toBeTruthy();
    expect(screen.getByText('Boletas (74)')).toBeTruthy();
    expect(screen.getByText('3,962.40')).toBeTruthy();
    expect(screen.getByText('EFECTIVO ESPERADO')).toBeTruthy();
    expect(screen.getByText('REPORTE INFORMATIVO')).toBeTruthy();
    expect(screen.queryByText(/B001-00004101/)).toBeNull();
    expect(screen.queryByText('Supervisor')).toBeNull();
  });

  it('prints the Z report of a closed shift with the count, the difference and where the cash went', () => {
    render(
      <ShiftTicket
        report={{
          ...base,
          shift: { ...base.shift, status: 'closed', closedAt: '2026-11-23T19:34:00-05:00' },
          cash: {
            ...base.cash,
            counted: '1840.30',
            difference: '-5.00',
            note: 'Vuelto mal entregado',
            fundKept: true,
            withdrawnAtClose: '1640.30',
          },
        }}
      />,
    );

    expect(screen.getByText('REPORTE Z')).toBeTruthy();
    expect(screen.getByText('CIERRE DE TURNO')).toBeTruthy();
    expect(screen.getByText('Z-0000481')).toBeTruthy();
    expect(screen.getByText('B001-00004101 a 00004174')).toBeTruthy();
    expect(screen.getByText('S/ 5.00 FALTANTE')).toBeTruthy();
    expect(screen.getByText('Motivo: Vuelto mal entregado')).toBeTruthy();
    expect(screen.getByText('Fondo siguiente turno')).toBeTruthy();
    expect(screen.getByText('1,640.30')).toBeTruthy();
    expect(screen.getByText('Supervisor')).toBeTruthy();
  });

  it('leaves out the destination of the cash for a shift closed before it was recorded', () => {
    render(
      <ShiftTicket
        report={{
          ...base,
          shift: { ...base.shift, status: 'closed', closedAt: '2026-11-23T19:34:00-05:00' },
          cash: { ...base.cash, counted: '1845.30', difference: '0.00' },
        }}
      />,
    );

    expect(screen.getByText('S/ 0.00 CUADRADO')).toBeTruthy();
    expect(screen.queryByText('DESTINO DEL EFECTIVO')).toBeNull();
  });
});
