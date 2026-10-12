import { afterEach, beforeEach, expect, it, vi } from 'vitest';

beforeEach(() => {
  vi.resetModules();
  localStorage.clear();
  document.cookie = 'CSRF_TOKEN=; Max-Age=0; path=/';
});

afterEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
  document.cookie = 'CSRF_TOKEN=; Max-Age=0; path=/';
});

it('exchanges the explicit impersonation credential and uses the issued CSRF cookie for branch selection', async () => {
  const grant = 'support-access-grant';
  localStorage.setItem('auth_token', JSON.stringify('stale-access-credential'));
  const requests: { path: string; authorization: string | null; csrf: string | null }[] = [];
  vi.stubGlobal(
    'fetch',
    vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const request =
        input instanceof Request
          ? input
          : new Request(new URL(String(input), window.location.origin), init);
      const path = new URL(request.url).pathname;
      requests.push({
        path,
        authorization: request.headers.get('Authorization'),
        csrf: request.headers.get('X-CSRF-Token'),
      });
      if (path === '/api/auth/impersonate/consume') {
        expect(await request.json()).toEqual({ token: grant });
        document.cookie = 'CSRF_TOKEN=support-csrf; path=/';
        return Response.json({ ok: true, user: { username: 'support-target' } });
      }
      return Response.json({ user: { username: 'support-target' } });
    }),
  );
  const { installAuthRecovery } = await import('@shared/api/authRecovery');
  const { authService } = await import('./authService');
  installAuthRecovery();

  await authService.consumeImpersonationToken(grant);
  await authService.selectBranch('branch-id');

  expect(requests).toEqual([
    { path: '/api/auth/impersonate/consume', authorization: `Bearer ${grant}`, csrf: null },
    { path: '/api/auth/select-branch', authorization: null, csrf: 'support-csrf' },
  ]);
  expect(localStorage.getItem('auth_token')).toBeNull();
});
