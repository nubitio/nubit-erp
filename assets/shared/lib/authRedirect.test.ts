import { describe, expect, it } from 'vitest';

import {
  buildLoginRedirectSearch,
  DEFAULT_POST_LOGIN_ROUTE,
  getHashRouterPath,
  getRedirectFromSearch,
  normalizeInternalRedirectTarget,
  resolveBranchSelectionRedirect,
  resolveLoginRedirect,
  resolvePostLoginRedirect,
  resolveRedirectFromSearch,
  toHashHref,
} from './authRedirect';

describe('authRedirect', () => {
  it('falls back to the default landing route when no redirect is present', () => {
    expect(resolveLoginRedirect('', '#/login')).toBe(DEFAULT_POST_LOGIN_ROUTE);
    expect(resolveRedirectFromSearch('')).toBe(DEFAULT_POST_LOGIN_ROUTE);
  });

  it('normalizes hash-router redirect values to canonical app-relative paths', () => {
    expect(normalizeInternalRedirectTarget('/#/user')).toBe('/user');
    expect(normalizeInternalRedirectTarget('#/user?tab=roles')).toBe('/user?tab=roles');
    expect(getRedirectFromSearch('?redirect=%2F%23%2Fuser')).toBe('/user');
  });

  it('builds guarded login redirects from canonical internal paths only', () => {
    expect(buildLoginRedirectSearch('/user', '?page=2')).toBe('?redirect=%2Fuser%3Fpage%3D2');
    expect(buildLoginRedirectSearch('/login', '?redirect=%2Fuser')).toBe('');
  });

  it('rejects unsafe and login-loop redirect targets', () => {
    expect(normalizeInternalRedirectTarget('https://evil.example/steal')).toBeNull();
    expect(normalizeInternalRedirectTarget('//evil.example/steal')).toBeNull();
    expect(normalizeInternalRedirectTarget('javascript:alert(1)')).toBeNull();
    expect(normalizeInternalRedirectTarget('/login')).toBeNull();
    expect(normalizeInternalRedirectTarget('/login?redirect=%2Fuser')).toBeNull();
    expect(normalizeInternalRedirectTarget('/../admin')).toBeNull();
    expect(resolvePostLoginRedirect('https://evil.example/steal')).toBe(DEFAULT_POST_LOGIN_ROUTE);
    expect(resolveRedirectFromSearch('?redirect=%2Flogin')).toBe(DEFAULT_POST_LOGIN_ROUTE);
  });

  it('keeps hash-router href output deterministic with safe fallback', () => {
    expect(getHashRouterPath('#/user')).toBe('/user');
    expect(getHashRouterPath('#/login?redirect=%2Fuser')).toBe(DEFAULT_POST_LOGIN_ROUTE);
    expect(toHashHref('/#/user')).toBe('/#/user');
    expect(toHashHref('/login')).toBe('/');
  });

  it('resolves equivalent direct-login and guarded-login redirect inputs identically', () => {
    expect(resolveLoginRedirect('?redirect=%2Fuser', '#/login?redirect=%2F%23%2Fuser')).toBe(
      '/user',
    );
    expect(resolveLoginRedirect('', '#/login?redirect=%2F%23%2Fuser')).toBe('/user');
    expect(resolveLoginRedirect('?redirect=%2F%23%2Fuser', '#/login')).toBe('/user');
    expect(resolveLoginRedirect('', '#/login?redirect=%2Flogin')).toBe(DEFAULT_POST_LOGIN_ROUTE);
    expect(resolveLoginRedirect('?redirect=https%3A%2F%2Fevil.example%2Fsteal', '#/login')).toBe(
      DEFAULT_POST_LOGIN_ROUTE,
    );
  });

  it('preserves a pre-resolved branch-selection redirect over later login-route inputs', () => {
    expect(resolveBranchSelectionRedirect('/user', '', '#/login')).toBe('/user');
    expect(resolveBranchSelectionRedirect('/#/user?tab=roles', '?redirect=%2Fpos', '#/login')).toBe(
      '/user?tab=roles',
    );
    expect(
      resolveBranchSelectionRedirect('https://evil.example/steal', '?redirect=%2Fuser', '#/login'),
    ).toBe('/user');
  });
});
