import { describe, expect, it } from 'vitest';

import { roleEndpoints } from './roleEndpoints';

describe('roleEndpoints', () => {
  it('uses the dedicated role-menu API resource for permission trees', () => {
    expect(roleEndpoints.menu(7)).toBe('/api/role-menu/7');
    expect(roleEndpoints.saveMenu()).toBe('/api/role-menu');
  });
});
