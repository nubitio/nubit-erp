import { describe, expect, it } from 'vitest';
import {
  annulsDocument,
  buildCreditNotePayload,
  creditNoteIssueErrors,
  isValidReturnQuantity,
  lineReturnCents,
  returnAllLines,
  returnSummary,
  selectedLines,
  type OriginLine,
} from './creditNoteIssueModel';

const lines: OriginLine[] = [
  {
    lineNumber: 1,
    productCode: 'ACC-0142',
    productName: 'Mouse',
    quantity: '10.000000',
    unitPrice: '24.900000',
    taxPercentage: '18.00',
    total: '249.00',
    id: 7,
    createdAt: '2026-10-01',
  },
  {
    lineNumber: 2,
    productName: 'Teclado',
    quantity: '10.000000',
    unitPrice: '39.900000',
    taxPercentage: '18.00',
    total: '399.00',
  },
];

describe('credit note issue model', () => {
  it('prices a partial return exactly', () => {
    expect(lineReturnCents(lines[0]!, '2')).toBe(4980n);
    expect(lineReturnCents(lines[0]!, '0.5')).toBe(1245n);
    expect(lineReturnCents(lines[0]!, 'abc')).toBeNull();
  });

  it('rejects zero, negative, malformed and excess quantities', () => {
    for (const bad of ['0', '-1', '', '1,5', '10.000001'])
      expect(isValidReturnQuantity(lines[0]!, bad)).toBe(false);
    expect(isValidReturnQuantity(lines[0]!, '10')).toBe(true);
  });

  it('summarises only selected lines and backs tax out of the gross total', () => {
    expect(returnSummary(lines, { 1: '2', 2: '0' })).toEqual({
      count: 1,
      total: '49.80',
      valueSold: '42.20',
      tax: '7.60',
    });
  });

  it('returns every line in full for annulling reasons', () => {
    expect(['01', '02', '06'].every(annulsDocument)).toBe(true);
    expect(annulsDocument('07')).toBe(false);
    expect(selectedLines(lines, returnAllLines(lines))).toHaveLength(2);
  });

  it('builds a payload without server-owned fields and with the chosen quantity', () => {
    const payload = buildCreditNotePayload({
      relatedTaxDocumentIri: '/api/tax-documents/abc',
      issueDate: '2026-10-30',
      reasonCode: '07',
      reasonDescription: '  2 mouses con falla  ',
      lines,
      returns: { 1: '2' },
    });
    expect(payload.reasonDescription).toBe('2 mouses con falla');
    expect(payload.lines).toHaveLength(1);
    expect(payload.lines[0]).toMatchObject({ lineNumber: 1, quantity: '2' });
    expect(payload.lines[0]).not.toHaveProperty('id');
    expect(payload.lines[0]).not.toHaveProperty('createdAt');
  });

  it('reports what is missing before submitting', () => {
    expect(
      creditNoteIssueErrors({ reasonCode: '', reasonDescription: ' ', lines, returns: {} }),
    ).toEqual({
      reason: expect.any(String),
      description: expect.any(String),
      lines: expect.any(String),
    });
    expect(
      creditNoteIssueErrors({
        reasonCode: '07',
        reasonDescription: 'x',
        lines,
        returns: { 1: '1' },
      }),
    ).toEqual({});
  });
});
