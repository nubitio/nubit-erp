import { describe, expect, it } from 'vitest';
import {
  buildLoginRedirectSearch,
  getRedirectFromSearch,
  normalizeInternalRedirectTarget,
  resolveBranchSelectionRedirect,
  resolveLoginRedirect,
  resolvePostLoginRedirect,
} from './authRedirect';

describe('normalizeInternalRedirectTarget', () => {
  it('keeps an internal path with its query', () => {
    expect(normalizeInternalRedirectTarget('/billing/sales?highlight=7')).toBe(
      '/billing/sales?highlight=7',
    );
  });

  it.each([
    ['/#/billing/sales', '/billing/sales'],
    ['#/billing/sales', '/billing/sales'],
    ['/#/', '/'],
    ['#', '/'],
  ])('reads a legacy hash target %j as %j', (input, expected) => {
    expect(normalizeInternalRedirectTarget(input)).toBe(expected);
  });

  it.each([
    'https://evil.example/x',
    '//evil.example/x',
    '/\\evil.example',
    'javascript:alert(1)',
    '/a/../b',
    'relative/path',
    '/pos#fragment',
    '',
    null,
    undefined,
  ])('rejects %j', (input) => {
    expect(normalizeInternalRedirectTarget(input)).toBeNull();
  });

  it('never redirects back to the login page', () => {
    expect(normalizeInternalRedirectTarget('/login')).toBeNull();
    expect(normalizeInternalRedirectTarget('/login/')).toBeNull();
    expect(normalizeInternalRedirectTarget('/login?redirect=/pos')).toBeNull();
  });
});

describe('login redirect round trip', () => {
  it('encodes the page the user came from', () => {
    expect(buildLoginRedirectSearch('/billing/sales', '?highlight=7')).toBe(
      '?redirect=%2Fbilling%2Fsales%3Fhighlight%3D7',
    );
  });

  it('builds nothing for pages that must not be returned to', () => {
    expect(buildLoginRedirectSearch('/login')).toBe('');
  });

  it('reads the redirect back from the query', () => {
    const search = buildLoginRedirectSearch('/catalog/products', '?page=2');
    expect(getRedirectFromSearch(search)).toBe('/catalog/products?page=2');
    expect(resolveLoginRedirect(search)).toBe('/catalog/products?page=2');
  });

  it('falls back to the default route', () => {
    expect(resolveLoginRedirect('')).toBe('/');
    expect(resolveLoginRedirect('?redirect=https%3A%2F%2Fevil.example')).toBe('/');
    expect(resolvePostLoginRedirect(null)).toBe('/');
  });
});

describe('resolveBranchSelectionRedirect', () => {
  it('prefers a preserved redirect over the query', () => {
    expect(resolveBranchSelectionRedirect('/pos', '?redirect=%2Fcatalog')).toBe('/pos');
  });

  it('uses the query when nothing was preserved or the preserved value is unsafe', () => {
    expect(resolveBranchSelectionRedirect(undefined, '?redirect=%2Fcatalog')).toBe('/catalog');
    expect(resolveBranchSelectionRedirect('//evil.example', '?redirect=%2Fcatalog')).toBe(
      '/catalog',
    );
  });

  it('defaults to the root', () => {
    expect(resolveBranchSelectionRedirect(undefined, '')).toBe('/');
  });
});
