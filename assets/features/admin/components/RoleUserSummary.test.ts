import { describe, expect, it } from 'vitest';
import { countUsersByRole } from './RoleUserSummary';

describe('countUsersByRole', () => {
  it('groups users by role name and keeps users without a role visible', () => {
    expect(
      countUsersByRole([
        { role: { id: 1, name: 'Cajero' } },
        { role: { id: 1, name: 'Cajero' } },
        { role: { id: 2, name: 'Administrador' } },
        { role: null },
      ]),
    ).toEqual([
      { name: 'Cajero', count: 2 },
      { name: 'Administrador', count: 1 },
      { name: 'Sin rol', count: 1 },
    ]);
  });
});
