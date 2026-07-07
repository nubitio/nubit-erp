import { afterEach, describe, expect, it } from 'vitest';

import { hasAnyPermission, hasPermission } from './permissions';

function storeUser(user: object) {
  localStorage.setItem('user', JSON.stringify(user));
}

afterEach(() => {
  localStorage.clear();
});

describe('hasPermission', () => {
  it('is false when no user is stored', () => {
    expect(hasPermission('sales.view')).toBe(false);
  });

  it('is false when the user has no permissions array', () => {
    storeUser({ username: 'juan' });
    expect(hasPermission('sales.view')).toBe(false);
  });

  it('matches granted keys exactly', () => {
    storeUser({ username: 'juan', permissions: ['sales.view', 'pos.access'] });

    expect(hasPermission('sales.view')).toBe(true);
    expect(hasPermission('sales.void')).toBe(false);
  });

  it('grants everything with the super admin wildcard', () => {
    storeUser({ username: 'admin', permissions: ['*'] });

    expect(hasPermission('sales.void')).toBe(true);
    expect(hasPermission('anything.at.all')).toBe(true);
  });
});

describe('hasAnyPermission', () => {
  it('is true when at least one key is granted', () => {
    storeUser({ username: 'juan', permissions: ['pos.access'] });

    expect(hasAnyPermission('sales.view', 'pos.access')).toBe(true);
    expect(hasAnyPermission('sales.view', 'sales.void')).toBe(false);
  });
});
