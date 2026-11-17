import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { collectSpaPaths } from './spaPaths';

/**
 * Contract with the backend (ADR-020, D6).
 *
 * `tests/Fixtures/spa-routes.json` is read by tests/Efact/Web/SpaRoutingTest.php,
 * which asserts that Symfony routes none of these paths anywhere except to the
 * SPA catch-all. This test keeps the file in step with the route tables, so a
 * new client route cannot silently collide with a server route.
 *
 * Regenerate after adding or removing a route:
 *   UPDATE_SPA_ROUTES=1 yarn test:unit assets/app/shell/spaPaths.test.ts
 */
// Vitest always runs from the project root (see package.json `test` scripts).
const fixture = join(process.cwd(), 'tests/Fixtures/spa-routes.json');

describe('SPA route manifest', () => {
  it('matches tests/Fixtures/spa-routes.json', () => {
    const current = collectSpaPaths();

    if (process.env['UPDATE_SPA_ROUTES'] === '1') {
      writeFileSync(fixture, `${JSON.stringify({ paths: current }, null, 2)}\n`);
    }

    expect(existsSync(fixture), 'run with UPDATE_SPA_ROUTES=1 to create the fixture').toBe(true);
    const stored = JSON.parse(readFileSync(fixture, 'utf8')) as { paths: string[] };

    expect(
      stored.paths,
      'SPA routes changed: regenerate with UPDATE_SPA_ROUTES=1 and review the backend collision test',
    ).toEqual(current);
  });

  it('lists the auth and full-screen routes that live outside app-routes', () => {
    const paths = collectSpaPaths();
    for (const expected of [
      '/login',
      '/impersonate',
      '/reset-password',
      '/accept-invitation',
      '/pos',
    ]) {
      expect(paths).toContain(expected);
    }
  });

  it('never contains catch-all or empty entries', () => {
    const paths = collectSpaPaths();
    expect(paths.every((path) => path.startsWith('/') && !path.includes('*'))).toBe(true);
  });
});
