import { describe, expect, it } from 'vitest';
import { gridReducer, initialGridState, type GridState } from './gridState';

const onPage = (page: number, extra: Partial<GridState> = {}) =>
  initialGridState({ page, ...extra });

describe('gridReducer', () => {
  describe('narrowing the result set returns to the first page in the same step', () => {
    it('search', () => {
      expect(gridReducer(onPage(4), { type: 'search', value: 'acme' })).toMatchObject({
        search: 'acme',
        page: 0,
      });
    });

    it('filter', () => {
      expect(
        gridReducer(onPage(4), { type: 'filter', name: 'status', value: 'active' }),
      ).toMatchObject({
        filters: { status: 'active' },
        page: 0,
      });
    });

    it('sort', () => {
      expect(
        gridReducer(onPage(4), { type: 'sort', sort: [{ selector: 'ruc', desc: false }] }),
      ).toMatchObject({ sort: [{ selector: 'ruc', desc: false }], page: 0 });
    });

    it('page size', () => {
      expect(gridReducer(onPage(4), { type: 'pageSize', size: 50 })).toMatchObject({
        pageSize: 50,
        page: 0,
      });
    });
  });

  describe('a change that changes nothing returns the same state (so nothing is refetched)', () => {
    it.each([
      ['search', { type: 'search', value: '' }],
      ['filter set to its current value', { type: 'filter', name: 'status', value: '' }],
      ['page', { type: 'page', page: 3 }],
      ['page size', { type: 'pageSize', size: 20 }],
      ['sort', { type: 'sort', sort: [] }],
    ] as const)('%s', (_label, action) => {
      const state = onPage(3);
      expect(gridReducer(state, action)).toBe(state);
    });

    it('the same sort again does not leave the current page', () => {
      const state = onPage(3, { sort: [{ selector: 'ruc', desc: true }] });
      expect(gridReducer(state, { type: 'sort', sort: [{ selector: 'ruc', desc: true }] })).toBe(
        state,
      );
    });
  });

  it('paging alone keeps everything else', () => {
    const state = onPage(0, { search: 'x', filters: { status: 'active' } });
    expect(gridReducer(state, { type: 'page', page: 2 })).toEqual({ ...state, page: 2 });
  });

  it('never goes below the first page', () => {
    expect(gridReducer(onPage(0), { type: 'page', page: -3 }).page).toBe(0);
    expect(gridReducer(onPage(2), { type: 'page', page: -1 }).page).toBe(0);
  });

  it('clearing a filter removes the key instead of keeping an empty value', () => {
    const state = onPage(0, { filters: { status: 'active', kind: 'x' } });
    expect(gridReducer(state, { type: 'filter', name: 'status', value: '' }).filters).toEqual({
      kind: 'x',
    });
  });

  it('does not mutate the previous state', () => {
    const state = onPage(2, { filters: { status: 'active' } });
    const snapshot = JSON.stringify(state);
    gridReducer(state, { type: 'filter', name: 'status', value: 'inactive' });
    expect(JSON.stringify(state)).toBe(snapshot);
  });

  it('reset replaces the whole state', () => {
    const fresh = initialGridState({ pageSize: 50 });
    expect(gridReducer(onPage(7, { search: 'x' }), { type: 'reset', state: fresh })).toBe(fresh);
  });
});
