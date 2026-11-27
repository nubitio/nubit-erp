import { describe, expect, it } from 'vitest';
import {
  manualPlan,
  planByDueDate,
  tenderDifference,
  toCents,
  validateManual,
  type OpenInvoice,
} from './collectionPlan';

const invoice = (
  id: string,
  balanceDue: string,
  dueDate: string,
  issuedDate = dueDate,
): OpenInvoice => ({
  id,
  balanceDue,
  dueDate,
  issuedDate,
});

const reference = [
  invoice('A', '800.00', '2026-10-01'),
  invoice('B', '1200.00', '2026-10-05'),
  invoice('C', '2400.00', '2026-10-10'),
  invoice('D', '1400.00', '2026-10-20'),
];

describe('planByDueDate', () => {
  it('spreads 2,500 as 800 / 1,200 / 500 and leaves the last invoice untouched', () => {
    const plan = planByDueDate(reference, toCents('2500'));

    expect(plan.lines.map((l) => `${l.invoiceId}:${l.cents / 100}`)).toEqual([
      'A:800',
      'B:1200',
      'C:500',
    ]);
    expect(plan.unappliedCents).toBe(0);
  });

  it('settles the oldest due date first even when it was issued later', () => {
    const plan = planByDueDate(
      [
        invoice('late-issued', '100.00', '2026-10-01', '2026-09-30'),
        invoice('early', '100.00', '2026-12-01', '2026-09-01'),
      ],
      10000,
    );

    expect(plan.lines.map((l) => l.invoiceId)).toEqual(['late-issued']);
  });

  it('returns what the invoices cannot absorb as the advance', () => {
    const plan = planByDueDate([invoice('A', '40.00', '2026-10-01')], 10000);

    expect(plan.lines).toEqual([{ invoiceId: 'A', cents: 4000 }]);
    expect(plan.unappliedCents).toBe(6000);
  });

  it('does not lose a cent to floating point', () => {
    const plan = planByDueDate(
      [invoice('A', '0.10', '2026-10-01'), invoice('B', '0.20', '2026-10-02')],
      toCents('0.30'),
    );

    expect(plan.lines.reduce((s, l) => s + l.cents, 0)).toBe(30);
    expect(plan.unappliedCents).toBe(0);
  });

  it('breaks ties by id so the preview is reproducible', () => {
    const plan = planByDueDate(
      [invoice('b', '10.00', '2026-10-01'), invoice('a', '10.00', '2026-10-01')],
      1000,
    );

    expect(plan.lines[0].invoiceId).toBe('a');
  });
});

describe('validateManual', () => {
  it('accepts a split within what each invoice owes and the amount received', () => {
    expect(validateManual(reference, 10000, [{ invoiceId: 'A', cents: 5000 }])).toBeNull();
  });

  it('rejects a line above what its invoice owes', () => {
    expect(validateManual(reference, 999999, [{ invoiceId: 'A', cents: 80001 }])).toMatch(
      /supera lo que debe/,
    );
  });

  it('rejects a split above the amount received', () => {
    expect(
      validateManual(reference, 10000, [
        { invoiceId: 'A', cents: 8000 },
        { invoiceId: 'B', cents: 8000 },
      ]),
    ).toMatch(/supera el monto/);
  });

  it('leaves the difference as the advance', () => {
    expect(manualPlan(10000, [{ invoiceId: 'A', cents: 6000 }]).unappliedCents).toBe(4000);
  });
});

describe('tenderDifference', () => {
  it('is zero when the tenders add up to the amount', () => {
    expect(tenderDifference(toCents('2500'), [toCents('1000'), toCents('1500')])).toBe(0);
  });

  it('is what is missing (positive) or left over (negative)', () => {
    expect(tenderDifference(10000, [4000])).toBe(6000);
    expect(tenderDifference(10000, [12000])).toBe(-2000);
  });
});
