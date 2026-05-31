/**
 * Shared Sale Domain Model
 *
 * These types are the single source of truth for all sale-related entities.
 * Both the POS Workspace and the Administrative Sale Editor must consume
 * these types — never define parallel domain models in feature modules.
 *
 * Discount scale convention: ALL discount percentages use 0–100 scale.
 * Never use fractions (0–1) for discount percentages anywhere in this codebase.
 */

/** Stable IRI identifier for API Platform resources (e.g. "/api/warehouses/1"). */
export type IRI = string;

/** ISO 4217 currency codes supported by the platform. */
export type CurrencyCode = 'PEN' | 'USD';

/**
 * SUNAT Catalogue 07 — Tipo de Afectación del IGV.
 *
 * Only the four operationally relevant top-level groups are modelled here.
 * The full SUNAT catalogue has subtypes (11–17 for gravado variants, 21 for
 * exonerado gratuito, 31–37 for inafecto variants) — extend this union if needed.
 *
 * | Code | Meaning                          | IGV treatment              |
 * |------|----------------------------------|----------------------------|
 * | '10' | Gravado – Operación Onerosa      | Full IGV at tenant rate    |
 * | '20' | Exonerado – Operación Onerosa    | 0% IGV, in exempt base     |
 * | '30' | Inafecto – Operación Onerosa     | 0% IGV, in inafecto base   |
 * | '40' | Exportación                      | 0% IGV, in inafecto base   |
 *
 * Default for all products when not explicitly set: `'10'` (gravado).
 */
export type SunatTaxType = '10' | '20' | '30' | '40';

/**
 * Discount expressed as a percentage in [0, 100].
 *
 * Convention: `percent` is ALWAYS stored and transmitted as 0–100.
 * `amount` is always computed (never supplied as input directly).
 */
export interface Discount {
  /** Percentage in [0, 100]. E.g. 10 means 10%. */
  percent: number;
  /** Absolute amount: baseAmount × (percent / 100). Computed, not an input field. */
  amount: number;
}

export interface ExchangeRate {
  currency: CurrencyCode;
  /** Units of PEN per 1 unit of the foreign currency (e.g. 3.85 means 1 USD = 3.85 PEN). */
  rate: number;
  /** ISO date string (YYYY-MM-DD). */
  date: string;
}

export interface Customer {
  id: number;
  iri: IRI;
  businessName: string;
  /** RUC (11 digits) or DNI (8 digits). */
  doc?: string;
  priceListId?: number;
}

export interface Warehouse {
  id: string;
  iri: IRI;
  name: string;
  branchId?: string | number;
}

export interface PaymentMethod {
  id: number;
  iri: IRI;
  name: string;
  isCash: boolean;
}

export interface SaleLine {
  /** Client-generated UUID — stable across edits within a session. */
  lineId: string;
  productId: number | string;
  productIri: IRI;
  name: string;
  sku?: string;
  quantity: number;
  unitPrice: number;
  discount: Discount;
  /** quantity × unitPrice − discount.amount */
  netAmount: number;
  /**
   * SUNAT IGV affectation type for this line.
   * Defaults to `'10'` (gravado) when the product has no explicit tax configuration.
   * Set from the product's `taxType` / `tip_afe_igv` field when available.
   */
  taxType: SunatTaxType;
  /**
   * IGV rate as a decimal fraction (e.g. 0.18 for 18%, 0.105 for 10.5%).
   * Only meaningful for gravado lines (taxType '10').
   * Sourced from `useTenantIgvRate()` — never hardcoded.
   */
  taxRate: number;
  saleUnitCode?: string;
  trackInventory: boolean;
}

export interface Payment {
  methodId: number;
  methodIri: IRI;
  methodName: string;
  amount: number;
  /** Cash-tendered amount (used for POS cash payments). */
  amountReceived?: number;
  /** Computed: max(0, amountReceived − amount). */
  changeAmount?: number;
}

/**
 * Computed sale totals.
 *
 * When all lines are gravado (taxType '10'), `gravadoBase === total` and
 * `exoneradoBase === inafectoBase === 0` — matching the pre-tax-type behaviour.
 *
 * `taxableBase` is kept as a backward-compatible alias for `gravadoBase`.
 */
export interface SaleTotals {
  /** Σ(line.netAmount) before the header-level discount is applied. */
  subtotal: number;
  /** Header-level discount applied after summing all lines. */
  discount: Discount;
  /**
   * Portion of (subtotal − discount) attributable to gravado lines (taxType '10').
   * This is the base to which IGV applies.
   */
  gravadoBase: number;
  /**
   * Portion of (subtotal − discount) attributable to exonerado lines (taxType '20').
   * Zero IGV; reported separately on SUNAT invoices.
   */
  exoneradoBase: number;
  /**
   * Portion of (subtotal − discount) attributable to inafecto / export lines
   * (taxType '30' or '40'). Zero IGV; reported separately on SUNAT invoices.
   */
  inafectoBase: number;
  /**
   * Alias for `gravadoBase`. Kept for backward compatibility with code written
   * before per-line tax types were supported.
   * @deprecated Use `gravadoBase` for new code.
   */
  taxableBase: number;
  /**
   * IGV extracted from `gravadoBase` (Peruvian tax-inclusive pricing).
   * Formula: gravadoBase × (taxRate / (1 + taxRate))
   */
  igv: number;
  /** Total amount the customer pays: gravadoBase + exoneradoBase + inafectoBase. */
  total: number;
}

export type SaleStatus = 'draft' | 'confirmed' | 'voided' | 'invoiced';

/** SUNAT-specific detraction data. Required for certain operation types and amounts. */
export interface DetractionData {
  catalogCode?: string;
  percentage?: number;
  amount?: number;
}

/** SUNAT additional references and metadata attached to a tax document. */
export interface SunatMetadata {
  guidesReferences?: string[];
  orderReferences?: string[];
  additionalInfo?: string;
}

export interface TaxDocument {
  id: string;
  type: 'invoice' | 'credit_note' | 'debit_note';
  series?: string;
  number?: string;
  sunatStatus?: string;
  publicReceiptUrl?: string;
}

export interface Sale {
  id?: number | string;
  iri?: IRI;
  /** ISO date string (YYYY-MM-DD). */
  date: string;
  customer: Customer;
  warehouse: Warehouse;
  paymentMethod: PaymentMethod;
  currency: CurrencyCode;
  exchangeRate?: ExchangeRate;
  priceListId?: number;
  lines: SaleLine[];
  /**
   * Payment entries. POS supports split payment (multiple entries).
   * The Admin editor currently uses a single entry via `paymentMethod`.
   * Always at least one entry when the sale is confirmed.
   */
  payments: Payment[];
  totals: SaleTotals;
  /** Header-level discount applied after summing all lines. */
  discount: Discount;
  note?: string;
  /** For internal use only — not printed on receipts. */
  internalObservation?: string;
  /** Whether to emit a SUNAT tax document (boleta/factura) for this sale. */
  emitInvoice: boolean;
  detractionData?: DetractionData;
  sunatMetadata?: SunatMetadata;
  taxDocuments?: TaxDocument[];
  status: SaleStatus;
}
