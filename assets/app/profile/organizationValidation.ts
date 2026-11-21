import type { VoucherSeries } from './organizationService';

export interface BranchDraft {
  name: string;
  address: string;
  phone: string;
}

export type BranchIssues = Partial<Record<keyof BranchDraft, string>>;

/** Rules for a branch; the optional fields are only checked when something was entered. */
export function validateBranch(draft: BranchDraft): BranchIssues {
  const issues: BranchIssues = {};
  if (draft.name.trim() === '') issues.name = 'Indica el nombre de la sucursal.';
  else if (draft.name.trim().length > 255)
    issues.name = 'El nombre no puede superar 255 caracteres.';
  if (draft.address.trim().length > 255) {
    issues.address = 'La dirección no puede superar 255 caracteres.';
  }
  if (draft.phone !== '' && !/^\d{6,10}$/.test(draft.phone)) {
    issues.phone = 'El teléfono debe tener entre 6 y 10 dígitos.';
  }
  return issues;
}

export interface SeriesDraft {
  branchId: string | null;
  typeCode: string | null;
  series: string;
  associatedSeries: string;
}

export type SeriesIssues = Partial<Record<keyof SeriesDraft, string>>;

/** SUNAT series are four letters or digits: F001, B001, FC01… */
export const SERIES_PATTERN = /^[A-Z0-9]{4}$/;

/** Keeps what a series can hold: letters and digits, capitalized, four at most. */
export const normalizeSeries = (value: string): string =>
  value
    .toUpperCase()
    .replace(/[^A-Z0-9]/g, '')
    .slice(0, 4);

/**
 * Rules for a voucher series. `others` are the series already registered (without the one being
 * edited): the same series for the same branch and type would make two correlatives collide.
 */
export function validateSeries(draft: SeriesDraft, others: VoucherSeries[]): SeriesIssues {
  const issues: SeriesIssues = {};
  if (!draft.branchId) issues.branchId = 'Elige la sucursal.';
  if (!draft.typeCode) issues.typeCode = 'Elige el tipo de comprobante.';

  if (!SERIES_PATTERN.test(draft.series)) {
    issues.series = 'La serie lleva 4 letras o números, por ejemplo F001.';
  } else if (
    others.some(
      (other) =>
        other.series === draft.series &&
        other.branch.id === draft.branchId &&
        other.voucherType.code === draft.typeCode,
    )
  ) {
    issues.series = 'Esa serie ya existe para esta sucursal y tipo de comprobante.';
  }

  if (draft.associatedSeries !== '' && !SERIES_PATTERN.test(draft.associatedSeries)) {
    issues.associatedSeries = 'La serie asociada lleva 4 letras o números.';
  }
  return issues;
}
