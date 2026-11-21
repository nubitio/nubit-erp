import { describe, expect, it } from 'vitest';
import { onlyDigits, validateCompany, type CompanyDraft } from './companyValidation';

const valid: CompanyDraft = {
  ruc: '20100070970',
  businessName: 'Empresa SAC',
  tradeName: '',
  fiscalAddress: 'Av. Siempre Viva 123',
  phone: '999999999',
  ubigeoCode: '',
  department: '',
  province: '',
  district: '',
  bankName: null,
  bankAccount: '',
  bankCci: '',
  detraccionAccount: '',
};

const with_ = (patch: Partial<CompanyDraft>) => validateCompany({ ...valid, ...patch });

describe('onlyDigits', () => {
  it('drops everything but digits and cuts at the limit', () => {
    expect(onlyDigits('19-1 234x5678', 8)).toBe('19123456');
  });
});

describe('validateCompany', () => {
  it('accepts a company with only the required data', () => {
    expect(validateCompany(valid)).toEqual({});
  });

  it('requires a valid RUC, business name and fiscal address', () => {
    const issues = with_({ ruc: '20100070971', businessName: ' ', fiscalAddress: '' });
    expect(Object.keys(issues).sort()).toEqual(['businessName', 'fiscalAddress', 'ruc']);
  });

  it('keeps names and address within what the columns hold', () => {
    expect(with_({ businessName: 'a'.repeat(256) }).businessName).toBeDefined();
    expect(with_({ tradeName: 'a'.repeat(256) }).tradeName).toBeDefined();
    expect(with_({ fiscalAddress: 'a'.repeat(501) }).fiscalAddress).toBeDefined();
    expect(with_({ tradeName: 'a'.repeat(255) }).tradeName).toBeUndefined();
  });

  it('takes a phone of 6 to 10 digits, or none', () => {
    expect(with_({ phone: '' }).phone).toBeUndefined();
    expect(with_({ phone: '12345' }).phone).toBeDefined();
    expect(with_({ phone: '12345678901' }).phone).toBeDefined();
    expect(with_({ phone: '98765abc1' }).phone).toBeDefined();
  });

  it('holds bank numbers to their length', () => {
    const bank = { bankName: 'BCP' };
    expect(with_({ ...bank, bankAccount: '123456789' }).bankAccount).toBeDefined();
    expect(with_({ ...bank, bankAccount: '1234567890123' }).bankAccount).toBeUndefined();
    expect(with_({ ...bank, bankAccount: '1'.repeat(21) }).bankAccount).toBeDefined();
    expect(with_({ ...bank, bankCci: '1'.repeat(19) }).bankCci).toBeDefined();
    expect(with_({ ...bank, bankCci: '1'.repeat(20) }).bankCci).toBeUndefined();
    expect(with_({ detraccionAccount: '1'.repeat(10) }).detraccionAccount).toBeDefined();
    expect(with_({ detraccionAccount: '1'.repeat(11) }).detraccionAccount).toBeUndefined();
  });

  it('asks for the bank when an account or CCI is given without it', () => {
    expect(with_({ bankAccount: '1234567890123' }).bankName).toBeDefined();
    expect(with_({ bankCci: '1'.repeat(20) }).bankName).toBeDefined();
    expect(with_({ bankName: 'BBVA', bankAccount: '1234567890123' }).bankName).toBeUndefined();
  });
});
