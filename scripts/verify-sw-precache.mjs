#!/usr/bin/env node
/**
 * Fails the build when the generated service worker precaches something production does not serve.
 *
 * The production image ships `public/build` (plus `sw.js` and the workbox runtime). A precache entry for any
 * other URL answers 404, Workbox rejects the install (`bad-precaching-response`), and the worker never takes
 * over: the offline POS silently stops working. Run it right after `vite build`, on the file the build wrote.
 */
import { existsSync, readFileSync } from 'node:fs';

const swPath = new URL('../public/sw.js', import.meta.url);

if (!existsSync(swPath)) {
  console.error('verify-sw-precache: public/sw.js was not generated.');
  process.exit(1);
}

const source = readFileSync(swPath, 'utf8');
const entries = [...source.matchAll(/\{url:"([^"]+)"/g)].map((match) => match[1]);
const stray = entries.filter((url) => !url.startsWith('build/'));

if (entries.length === 0) {
  console.error('verify-sw-precache: public/sw.js precaches nothing; the precache list was not found.');
  process.exit(1);
}

if (stray.length > 0) {
  console.error(
    `verify-sw-precache: the service worker precaches files that are not shipped to production:\n  ${stray.join('\n  ')}\n` +
      'Workbox would fail the install with bad-precaching-response. Keep precache entries under build/.',
  );
  process.exit(1);
}

console.log(`verify-sw-precache: ${entries.length} precache entries, all under build/.`);
