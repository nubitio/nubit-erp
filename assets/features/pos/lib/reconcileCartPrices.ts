import type { CartItem, PosItem } from '@shared/pos/types';

export type CartPriceChange = { id: string; name: string; from: string; to: string };

/**
 * Brings the order in progress in line with the prices the till is now quoting
 * (after a product or a price list changed, or another customer was picked).
 *
 * A line remembers `quotedPrice`, the catalogue price it was given. Compared with
 * its current price that tells two kinds of line apart:
 *   - following the catalogue (price == quotedPrice): takes the new price, and is
 *     reported so the cashier is not surprised by a changed total;
 *   - changed by hand (price != quotedPrice): keeps what the cashier set. Only the
 *     reference moves, so the line is still recognised as an override.
 * Lines of an older draft have no `quotedPrice` and are treated as following.
 * Returns the same array when nothing changes.
 */
export const reconcileCartPrices = (
  cart: CartItem[],
  catalog: PosItem[],
): { cart: CartItem[]; changes: CartPriceChange[] } => {
  if (cart.length === 0) return { cart, changes: [] };

  const priceById = new Map(catalog.map((item) => [item.id, item.salePrice]));
  const changes: CartPriceChange[] = [];
  let changed = false;

  const next = cart.map((line) => {
    const current = priceById.get(line.id);
    if (current === undefined) return line;

    const quoted = line.quotedPrice ?? line.salePrice;
    if (current === quoted && line.quotedPrice !== undefined) return line;
    if (current === quoted) return { ...line, quotedPrice: current };

    changed = true;
    if (line.salePrice !== quoted) {
      return { ...line, quotedPrice: current };
    }
    changes.push({ id: line.id, name: line.name, from: line.salePrice, to: current });
    return { ...line, salePrice: current, quotedPrice: current };
  });

  return { cart: changed ? next : cart, changes };
};
