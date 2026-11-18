const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function productNameInitials(name: string): string {
  const words = name
    .trim()
    .split(/[\s/_-]+/)
    .filter((token) => !/\d/.test(token))
    .map((word) => word.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/g, ''))
    .filter((word) => word.length > 0);

  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase();
  }

  if (words.length === 1) {
    return words[0].slice(0, 2).toUpperCase();
  }

  return 'PR';
}

export function isInternalDocumentCode(code: string | null | undefined): boolean {
  if (!code) {
    return true;
  }

  const trimmed = code.trim();
  return trimmed === '' || UUID_RE.test(trimmed);
}

export function publicSaleCode(code: string | null | undefined): string | null {
  if (!code || isInternalDocumentCode(code)) {
    return null;
  }

  const trimmed = code.trim();
  const match = /^([A-Za-z]+\d*)-(\d+)$/.exec(trimmed);
  if (!match) return trimmed;
  return `${match[1].toUpperCase()}-${match[2].padStart(8, '0')}`;
}

export function formatPosReceiptLabel(
  code: string | null | undefined,
  documentType?: string | null,
): string | null {
  const publicCode = publicSaleCode(code);
  if (!publicCode) {
    return null;
  }

  const type = (documentType ?? '').trim().toUpperCase();
  const looksLikeFactura = type === '01' || type === 'FACTURA' || /^[FE]\d/i.test(publicCode);
  const looksLikeBoleta = type === '03' || type === 'BOLETA' || /^B\d/i.test(publicCode);

  if (looksLikeFactura) {
    return `Factura ${publicCode}`;
  }

  if (looksLikeBoleta) {
    return `Boleta ${publicCode}`;
  }

  return `Comprobante ${publicCode}`;
}

export function formatStockCount(stockValue: number): string {
  const amount = stockValue % 1 === 0 ? stockValue.toFixed(0) : stockValue.toFixed(1);
  return `${amount} und`;
}

const PIECE_PREFIX_RE = /^(\d{1,4})\s+(\S.*)$/s;

/**
 * Piece count encoded as a leading integer in a weighted line's description
 * ("2 Pechuga de pollo" → 2). Returns 0 when there is no custom description.
 */
export function linePieceCount(lineDescription: string | null | undefined): number {
  const match = PIECE_PREFIX_RE.exec((lineDescription ?? '').trim());
  return match ? Number.parseInt(match[1], 10) : 0;
}

/**
 * Rewrites a line description with a new piece count, keeping any manual text
 * after the prefix. Returns '' when the result equals the product name so the
 * line goes back to the catalog description.
 */
export function withLinePieceCount(
  lineDescription: string | null | undefined,
  productName: string,
  pieces: number,
): string {
  const current = (lineDescription ?? '').trim();
  const match = PIECE_PREFIX_RE.exec(current);
  const base = (match ? match[2] : current).trim() || productName;
  const next = pieces > 0 ? `${Math.floor(pieces)} ${base}` : base;
  return next === productName ? '' : next.slice(0, 255);
}
