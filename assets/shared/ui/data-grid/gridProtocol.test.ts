import { describe, expect, it } from 'vitest';
import {
  MAX_SEARCH_LENGTH,
  parseGridResponse,
  toExportParams,
  toGridParams,
  type GridQuery,
} from './gridProtocol';

const base: GridQuery = {
  page: 0,
  pageSize: 20,
  search: '',
  searchFields: ['ruc', 'businessName'],
  sort: [],
  filter: [],
};

describe('toGridParams', () => {
  it('converts the 0-based page to the 1-based page API Platform expects', () => {
    expect(toGridParams({ ...base, page: 0 })).toMatchObject({ page: 1, itemsPerPage: 20 });
    expect(toGridParams({ ...base, page: 4, pageSize: 50 })).toMatchObject({
      page: 5,
      itemsPerPage: 50,
    });
  });

  it('sends nothing optional for an empty query', () => {
    expect(Object.keys(toGridParams(base)).sort()).toEqual(['itemsPerPage', 'page']);
  });

  it('encodes sort as JSON descriptors', () => {
    const params = toGridParams({
      ...base,
      sort: [
        { selector: 'businessName', desc: false },
        { selector: 'ruc', desc: true },
      ],
    });

    expect(JSON.parse(params['sort'] as string)).toEqual([
      { selector: 'businessName', desc: false },
      { selector: 'ruc', desc: true },
    ]);
  });

  it('sends a single filter leaf bare', () => {
    const params = toGridParams({ ...base, filter: [['isActive', '=', true]] });

    expect(JSON.parse(params['filter'] as string)).toEqual(['isActive', '=', true]);
  });

  it('joins several leaves with "and"', () => {
    const params = toGridParams({
      ...base,
      filter: [
        ['isActive', '=', true],
        ['ruc', 'startswith', '20'],
      ],
    });

    expect(JSON.parse(params['filter'] as string)).toEqual([
      ['isActive', '=', true],
      'and',
      ['ruc', 'startswith', '20'],
    ]);
  });

  describe('search', () => {
    it('sends the text with the columns to scan, trimmed', () => {
      expect(toGridParams({ ...base, search: '  acme ' })).toMatchObject({
        searchValue: 'acme',
        searchExpr: ['ruc', 'businessName'],
      });
    });

    it.each(['', '   '])('sends nothing for blank text %j', (search) => {
      const params = toGridParams({ ...base, search });

      expect(params).not.toHaveProperty('searchValue');
      expect(params).not.toHaveProperty('searchExpr');
    });

    it('sends nothing when no column is searchable (the server would ignore it anyway)', () => {
      expect(toGridParams({ ...base, search: 'x', searchFields: [] })).not.toHaveProperty(
        'searchValue',
      );
    });

    it('never sends more than the server accepts', () => {
      const params = toGridParams({ ...base, search: 'a'.repeat(MAX_SEARCH_LENGTH + 50) });

      expect((params['searchValue'] as string).length).toBe(MAX_SEARCH_LENGTH);
    });
  });

  it('does not mutate the query it is given', () => {
    const query: GridQuery = { ...base, filter: [['isActive', '=', true]] };
    const snapshot = JSON.stringify(query);

    toGridParams(query);

    expect(JSON.stringify(query)).toBe(snapshot);
  });
});

describe('toExportParams', () => {
  it('keeps filters, sort and search but drops pagination', () => {
    const params = toExportParams({
      ...base,
      page: 3,
      search: 'acme',
      sort: [{ selector: 'ruc', desc: false }],
      filter: [['isActive', '=', true]],
    });

    expect(params).not.toHaveProperty('page');
    expect(params).not.toHaveProperty('itemsPerPage');
    expect(params).toHaveProperty('sort');
    expect(params).toHaveProperty('filter');
    expect(params).toHaveProperty('searchValue', 'acme');
  });
});

describe('parseGridResponse', () => {
  const rows = [{ id: 1 }, { id: 2 }];

  it.each([
    ['hydra:member', { 'hydra:member': rows }],
    ['member', { member: rows }],
    ['a bare array', rows],
  ])('reads rows from %s', (_name, body) => {
    expect(parseGridResponse(body).rows).toEqual(rows);
  });

  it('prefers the X-Total-Count header, whatever its case', () => {
    expect(parseGridResponse({ member: rows, totalItems: 9 }, { 'x-total-count': '42' })).toEqual({
      rows,
      totalCount: 42,
    });
    expect(parseGridResponse({ member: rows }, { 'X-Total-Count': '7' }).totalCount).toBe(7);
  });

  it('falls back to the body total, then to the page length', () => {
    expect(parseGridResponse({ member: rows, 'hydra:totalItems': 30 }).totalCount).toBe(30);
    expect(parseGridResponse({ member: rows, totalItems: 31 }).totalCount).toBe(31);
    expect(parseGridResponse({ member: rows }).totalCount).toBe(2);
  });

  it('ignores a header that is not a number', () => {
    expect(parseGridResponse({ member: rows }, { 'x-total-count': 'nope' }).totalCount).toBe(2);
    expect(parseGridResponse({ member: rows }, { 'x-total-count': '' }).totalCount).toBe(2);
  });

  it('survives an empty or malformed body', () => {
    expect(parseGridResponse(null)).toEqual({ rows: [], totalCount: 0 });
    expect(parseGridResponse(undefined)).toEqual({ rows: [], totalCount: 0 });
    expect(parseGridResponse({ member: 'not a list' }).rows).toEqual([]);
  });
});
