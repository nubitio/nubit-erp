import { describe, expect, it } from 'vitest';
import { receivableAging, type ReceivableSummary } from './receivableAging';

describe('receivableAging', () => {
  it('groups older overdue balances exactly and keeps current balances separate', () => {
    const summary: ReceivableSummary = {
      currency: 'PEN',
      totalBalance: '101.80',
      debtorCount: 3,
      overdueTotal: '1.80',
      notYetDueTotal: '100.00',
      aging: [
        { bucket: 'current', label: 'Al día', total: '100.00' },
        { bucket: '1-30', label: '', total: '0.30' },
        { bucket: '31-60', label: '', total: '1.20' },
        { bucket: '61-90', label: '', total: '0.10' },
        { bucket: '90+', label: '', total: '0.20' },
      ],
    };
    expect(receivableAging(summary).map((entry) => entry.amount)).toEqual([
      '100.00',
      '0.30',
      '1.20',
      '0.30',
    ]);
    expect(
      receivableAging({ ...summary, totalBalance: '0.00', notYetDueTotal: '0.00', aging: [] }).map(
        (entry) => entry.amount,
      ),
    ).toEqual(['0.00', '0.00', '0.00', '0.00']);
  });
});
