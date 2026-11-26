import { describe, expect, it } from 'vitest';
import type { VoucherSeries } from './organizationService';
import {
  normalizeSeries,
  validateBranch,
  validateSeries,
  type SeriesDraft,
} from './organizationValidation';

describe('validateBranch', () => {
  it('needs a name and keeps optional data in range', () => {
    expect(validateBranch({ name: 'Principal', address: '', phone: '' })).toEqual({});
    expect(validateBranch({ name: ' ', address: '', phone: '' }).name).toBeDefined();
    expect(validateBranch({ name: 'a'.repeat(256), address: '', phone: '' }).name).toBeDefined();
    expect(
      validateBranch({ name: 'A', address: 'a'.repeat(256), phone: '' }).address,
    ).toBeDefined();
    expect(validateBranch({ name: 'A', address: '', phone: '12345' }).phone).toBeDefined();
    expect(validateBranch({ name: 'A', address: '', phone: '987654321' }).phone).toBeUndefined();
  });
});

describe('normalizeSeries', () => {
  it('capitalizes and drops anything that is not a letter or digit', () => {
    expect(normalizeSeries('f-0 01x9')).toBe('F001');
  });
});

const existing: VoucherSeries = {
  id: 1,
  branch: { id: 'b1', name: 'Principal' },
  voucherType: { code: '01', name: 'Factura' },
  series: 'F001',
  associatedSeries: null,
  isActive: true,
};

const draft = (patch: Partial<SeriesDraft> = {}): SeriesDraft => ({
  branchId: 'b1',
  typeCode: '03',
  series: 'B001',
  associatedSeries: '',
  ...patch,
});

describe('validateSeries', () => {
  it('accepts a four-character series for a branch and type', () => {
    expect(validateSeries(draft(), [existing])).toEqual({});
  });

  it('asks for the branch, the type and a well-formed series', () => {
    const issues = validateSeries(draft({ branchId: null, typeCode: null, series: 'F1' }), []);
    expect(Object.keys(issues).sort()).toEqual(['branchId', 'series', 'typeCode']);
  });

  it('reserves the series company-wide per voucher type, even when inactive', () => {
    const same = draft({ typeCode: '01', series: 'F001' });
    expect(validateSeries(same, [existing]).series).toBeDefined();
    expect(
      validateSeries({ ...same, branchId: 'b2' }, [{ ...existing, isActive: false }]).series,
    ).toContain('Principal');
    expect(validateSeries({ ...same, typeCode: '03' }, [existing]).series).toBeUndefined();
  });

  it('allows editing other fields of an existing legacy duplicate without reassigning it', () => {
    const legacy = { ...existing, id: 2, branch: { id: 'b2', name: 'Otra sucursal' } };
    const same = draft({ branchId: 'b2', typeCode: '01', series: 'F001' });
    expect(validateSeries(same, [existing, legacy], legacy)).toEqual({});
    expect(
      validateSeries({ ...same, branchId: 'b3' }, [existing, legacy], legacy).series,
    ).toBeDefined();
  });

  it('checks the associated series only when given', () => {
    expect(validateSeries(draft({ associatedSeries: 'F00' }), []).associatedSeries).toBeDefined();
    expect(
      validateSeries(draft({ associatedSeries: 'F001' }), []).associatedSeries,
    ).toBeUndefined();
  });
});
