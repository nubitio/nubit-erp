import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * A few screens still read these names, but no theme defines them. assets/styles.scss aliases
 * them to the Nubit tokens. If a stylesheet starts using another undefined legacy name, or the
 * alias is removed, the accent colour silently disappears (no fallback) or turns the old blue.
 */
const LEGACY_ALIASES = ['--primary-color', '--primary-contrast'];
const ASSETS = join(__dirname, '..');

function scssFiles(dir: string): string[] {
  return readdirSync(dir).flatMap((entry) => {
    const path = join(dir, entry);
    if (statSync(path).isDirectory()) return scssFiles(path);
    return path.endsWith('.scss') ? [path] : [];
  });
}

describe('legacy colour tokens', () => {
  const globalStyles = readFileSync(join(ASSETS, 'styles.scss'), 'utf8');

  it.each(LEGACY_ALIASES)('%s is aliased to a Nubit token in styles.scss', (token) => {
    expect(globalStyles).toMatch(new RegExp(`${token}:\\s*var\\(--(accent|on-accent)-color`));
  });

  it('no stylesheet defines them differently or reads them from a name nobody defines', () => {
    const offenders: string[] = [];
    for (const file of scssFiles(ASSETS)) {
      if (file.endsWith('styles.scss')) continue;
      const source = readFileSync(file, 'utf8');
      for (const token of LEGACY_ALIASES) {
        if (new RegExp(`^\\s*${token}\\s*:`, 'm').test(source))
          offenders.push(`${file} redefines ${token}`);
      }
    }
    expect(offenders).toEqual([]);
  });
});
