import { describe, expect, it } from 'vitest';
import type { CartItem, PosItem } from '@shared/pos/types';
import { reconcileCartPrices } from './reconcileCartPrices';

const catalog = (prices: Record<string, string>) =>
  Object.entries(prices).map(([id, salePrice]) => ({ id, name: id, salePrice }) as PosItem);

const line = (id: string, salePrice: string, quotedPrice?: string) =>
  ({ id, name: id.toUpperCase(), salePrice, quotedPrice, quantity: 1 }) as CartItem;

describe('reconcileCartPrices', () => {
  it('reprices a line that follows the catalogue and reports it', () => {
    const { cart, changes } = reconcileCartPrices(
      [line('pollo', '9.80', '9.80')],
      catalog({ pollo: '10.40' }),
    );
    expect(cart[0]).toMatchObject({ salePrice: '10.40', quotedPrice: '10.40' });
    expect(changes).toEqual([{ id: 'pollo', name: 'POLLO', from: '9.80', to: '10.40' }]);
  });

  it('keeps a price the cashier set by hand, and still tracks the new reference', () => {
    const { cart, changes } = reconcileCartPrices(
      [line('pollo', '9.50', '9.80')],
      catalog({ pollo: '10.40' }),
    );
    expect(cart[0]).toMatchObject({ salePrice: '9.50', quotedPrice: '10.40' });
    expect(changes).toEqual([]);
  });

  it('treats a line from an older draft (no quotedPrice) as following the catalogue', () => {
    const { cart, changes } = reconcileCartPrices(
      [line('pollo', '9.80')],
      catalog({ pollo: '10.40' }),
    );
    expect(cart[0].salePrice).toBe('10.40');
    expect(changes).toHaveLength(1);
  });

  it('returns the same cart when nothing changed', () => {
    const original = [line('pollo', '10.40', '10.40')];
    expect(reconcileCartPrices(original, catalog({ pollo: '10.40' })).cart).toBe(original);
  });

  it('leaves lines that are not in the catalogue alone', () => {
    const original = [line('ghost', '5.00', '5.00')];
    const result = reconcileCartPrices(original, catalog({ pollo: '10.40' }));
    expect(result.cart).toBe(original);
    expect(result.changes).toEqual([]);
  });

  it('applies the new tariff to every followed line but reports each one', () => {
    const { cart, changes } = reconcileCartPrices(
      [line('a', '1.00', '1.00'), line('b', '2.00', '2.00'), line('c', '3.00', '3.00')],
      catalog({ a: '1.10', b: '2.00', c: '3.30' }),
    );
    expect(cart.map((l) => l.salePrice)).toEqual(['1.10', '2.00', '3.30']);
    expect(changes.map((c) => c.id)).toEqual(['a', 'c']);
  });
});
