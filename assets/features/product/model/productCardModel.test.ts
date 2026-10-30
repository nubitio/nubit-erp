import { describe, expect, it } from 'vitest';
import { productCardModel } from './productCardModel';

describe('product catalog card', () => {
  it('does not present absent or invalid stock as zero', () => {
    for (const stock of [undefined, null, '', ' ', 'invalid', Infinity]) {
      const model = productCardModel({ inventoryBehavior: 'TRACKED', stock });
      expect(model.stockLabel).toBe('Consultar existencias');
      expect(model.tone).toBe('secondary');
    }
    expect(productCardModel({}).stockLabel).toBe('Consultar existencias');
  });

  it('uses an explicitly published quantity without aggregating warehouses', () => {
    expect(productCardModel({ inventoryBehavior: 'TRACKED', stock: '0' }).tone).toBe('danger');
    expect(productCardModel({ inventoryBehavior: 'TRACKED', stock: '2', minStock: '5' }).stockLabel).toBe('Stock bajo · 2');
    expect(productCardModel({ inventoryBehavior: 'TRACKED', stock: '8', minStock: '5' }).tone).toBe('success');
    expect(productCardModel({ inventoryBehavior: 'TRACKED', stocks: [{ stock: 50 }] }).stockLabel).toBe('Consultar existencias');
    expect(productCardModel({ inventoryBehavior: 'NOT_TRACKED', stock: 0 }).stockLabel).toBe('Sin control de stock');
  });

  it('reuses API labels and image paths, without exposing an IRI as a category name', () => {
    const model = productCardModel({ name: 'Mouse', sku: 'M-01', category: { name: 'Accesorios' }, salePrice: '24.90', photo: { path: '/uploads/mouse.png' } });
    expect(model).toMatchObject({ name: 'Mouse', code: 'M-01', category: 'Accesorios', image: '/uploads/mouse.png' });
    expect(model.price).toContain('24.90');
    expect(productCardModel({ category: '/api/categories/1' }).category).toBe('Categoría no disponible');
  });
});
