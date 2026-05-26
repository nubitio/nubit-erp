import { describe, expect, it } from 'vitest';
import { HydraRemoteDataSource } from './HydraRemoteDataSource';

const makeSource = (overrides = {}) =>
  new HydraRemoteDataSource({
    url: '/api/products',
    idField: 'id',
    ...overrides,
  });

// ── prepareLoadOptions ────────────────────────────────────────────────────────

describe('HydraRemoteDataSource.prepareLoadOptions', () => {
  it('passes through load options unchanged when no defaults are set', () => {
    const ds = makeSource();
    const result = ds.prepareLoadOptions({ filter: [], sort: [] });
    expect(result.filter).toEqual([]);
    expect(result.sort).toEqual([]);
  });

  it('strips searchOperation from load options', () => {
    const ds = makeSource();
    const result = ds.prepareLoadOptions({ searchOperation: 'contains' });
    expect(result.searchOperation).toBeUndefined();
  });

  it('strips searchValue when searchExpr is missing', () => {
    const ds = makeSource();
    const result = ds.prepareLoadOptions({ searchValue: 'test' });
    expect(result.searchValue).toBeUndefined();
  });

  it('keeps searchValue when searchExpr is present', () => {
    const ds = makeSource();
    const result = ds.prepareLoadOptions({ searchExpr: 'name', searchValue: 'test' });
    expect(result.searchValue).toBe('test');
    expect(result.searchExpr).toBe('name');
  });

  it('inlines a single three-element filter tuple into nested filter array', () => {
    const ds = makeSource();
    const result = ds.prepareLoadOptions({ filter: ['name', '=', 'laptop'] });
    expect(result.filter).toEqual([['name', '=', 'laptop']]);
  });

  it('prepends defaultFilterRules before user filters', () => {
    const ds = makeSource({
      defaultFilterRules: [['active', '=', true]],
    });
    const result = ds.prepareLoadOptions({ filter: ['name', '=', 'test'] });
    expect(result.filter).toEqual([['active', '=', true], ['name', '=', 'test']]);
  });

  it('skips the default id-only sort when no defaultSortRules are set', () => {
    const ds = makeSource();
    const result = ds.prepareLoadOptions({ sort: [{ selector: 'id', desc: false }] });
    expect(result.sort).toEqual([]);
  });

  it('applies defaultSortRules when the only sort is by idField', () => {
    const ds = makeSource({ defaultSortRules: [{ selector: 'name', desc: false }] });
    const result = ds.prepareLoadOptions({ sort: [{ selector: 'id', desc: false }] });
    expect(result.sort).toEqual([{ selector: 'name', desc: false }]);
  });
});

// ── makeFilterRules ───────────────────────────────────────────────────────────

describe('HydraRemoteDataSource.makeFilterRules', () => {
  it('serializes filter rules to query string format', () => {
    const ds = makeSource();
    const result = ds.makeFilterRules([
      { field: 'name', operator: 'contains', value: 'laptop' },
    ]);
    expect(result).toBe('filter[]=["name","contains","laptop"]');
  });

  it('joins multiple filter rules with &', () => {
    const ds = makeSource();
    const result = ds.makeFilterRules([
      { field: 'active', operator: '=', value: 'true' },
      { field: 'name', operator: 'startswith', value: 'A' },
    ]);
    expect(result).toBe('filter[]=["active","=","true"]&filter[]=["name","startswith","A"]');
  });

  it('returns empty string for an empty rule list', () => {
    const ds = makeSource();
    expect(ds.makeFilterRules([])).toBe('');
  });
});
