import { SharedUtils } from '@shared/lib/SharedUtils';

/** What the company form edits; text as typed, banks as the chosen option. */
export interface CompanyDraft {
  ruc: string;
  businessName: string;
  tradeName: string;
  fiscalAddress: string;
  phone: string;
  ubigeoCode: string;
  department: string;
  province: string;
  district: string;
  bankName: string | null;
  bankAccount: string;
  bankCci: string;
  detraccionAccount: string;
}

export type CompanyIssues = Partial<Record<keyof CompanyDraft, string>>;

/** Longest value each digits-only field takes; also the `maxLength` of its input. */
export const MAX_DIGITS = { ruc: 11, phone: 10, bankAccount: 20, bankCci: 20, detraccion: 11 };

/** Keeps only the digits of what was typed or pasted, up to `max`. */
export const onlyDigits = (value: string, max: number): string =>
  value.replace(/\D/g, '').slice(0, max);

/**
 * The same rules the API enforces on `Company`, so a mistake is caught while typing instead of
 * after a round trip. Optional fields are only checked when something was entered.
 */
export function validateCompany(draft: CompanyDraft): CompanyIssues {
  const issues: CompanyIssues = {};
  const filled = (value: string) => value.trim() !== '';

  if (draft.ruc.length !== 11 || !SharedUtils.validateRUC(draft.ruc)) {
    issues.ruc = 'El RUC debe tener 11 dígitos válidos.';
  }

  if (!filled(draft.businessName)) issues.businessName = 'Indica la razón social.';
  else if (draft.businessName.trim().length > 255) {
    issues.businessName = 'La razón social no puede superar 255 caracteres.';
  }

  if (draft.tradeName.trim().length > 255) {
    issues.tradeName = 'El nombre comercial no puede superar 255 caracteres.';
  }

  if (!filled(draft.fiscalAddress)) issues.fiscalAddress = 'Indica el domicilio fiscal.';
  else if (draft.fiscalAddress.trim().length > 500) {
    issues.fiscalAddress = 'El domicilio fiscal no puede superar 500 caracteres.';
  }

  if (filled(draft.phone) && !/^\d{6,10}$/.test(draft.phone)) {
    issues.phone = 'El teléfono debe tener entre 6 y 10 dígitos.';
  }

  if (filled(draft.bankAccount) && !/^\d{10,20}$/.test(draft.bankAccount)) {
    issues.bankAccount = 'La cuenta bancaria debe tener entre 10 y 20 dígitos.';
  }
  if (filled(draft.bankCci) && !/^\d{20}$/.test(draft.bankCci)) {
    issues.bankCci = 'El CCI debe tener 20 dígitos.';
  }
  if (filled(draft.detraccionAccount) && !/^\d{11}$/.test(draft.detraccionAccount)) {
    issues.detraccionAccount = 'La cuenta de detracciones debe tener 11 dígitos.';
  }

  // An account number means nothing without the bank that holds it.
  if (!draft.bankName && (filled(draft.bankAccount) || filled(draft.bankCci))) {
    issues.bankName = 'Elige el banco de la cuenta.';
  }

  return issues;
}
