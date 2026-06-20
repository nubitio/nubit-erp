/**
 * Per-product price entry returned by GET /api/price-lists/{id}/prices.
 * Used in the PriceEditorGrid dialog.
 */
export interface PriceListItemDto {
  id: string;
  name: string;
  /** Effective price as a decimal string to avoid floating-point rounding. */
  price: string;
  /** How the price was derived: 'override' | 'multiplier' | 'base' */
  priceSource: 'override' | 'multiplier' | 'base';
}

/**
 * Slim entry returned by GET /api/price-lists/{id}/apply.
 * Used by the sale form to auto-populate line item prices.
 */
export interface PriceListApplyDto {
  id: string;
  price: string;
}
