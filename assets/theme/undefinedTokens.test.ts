import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * An undefined `var(--x)` without a fallback makes the declaration invalid (the colour, border or
 * font simply does not apply); with a fallback it paints a hard-coded value that ignores the theme.
 * This guard fails when a stylesheet or inline style reads a custom property that nothing defines.
 */
const ROOT = join(__dirname, '..', '..');
const ASSETS = join(ROOT, 'assets');

/**
 * Read only with a fallback and set per component at runtime or not yet themed. Keep this list
 * short: add the definition (see assets/legacy-tokens.scss) instead of growing it.
 */
const KNOWN_UNDEFINED_WITH_FALLBACK = new Set(['--pos-warning-border']);

function walk(dir: string, extensions: string[]): string[] {
  return readdirSync(dir).flatMap((entry) => {
    const path = join(dir, entry);
    const info = statSync(path);
    if (info.isDirectory()) return entry === 'node_modules' ? [] : walk(path, extensions);
    return extensions.some((extension) => path.endsWith(extension)) ? [path] : [];
  });
}

function definedProperties(): Set<string> {
  const files = [
    ...walk(ASSETS, ['.scss', '.css', '.ts', '.tsx']),
    ...walk(join(ROOT, 'node_modules', '@nubitio'), ['.css']).filter((file) =>
      file.includes('/dist/'),
    ),
  ];
  const defined = new Set<string>();
  for (const file of files) {
    for (const match of readFileSync(file, 'utf8').matchAll(/(--[a-zA-Z0-9_-]+)['"]?\s*:/g))
      defined.add(match[1]);
  }
  return defined;
}

describe('custom properties', () => {
  it('every var(--x) read by the app is defined somewhere', () => {
    const defined = definedProperties();
    const offenders: string[] = [];

    for (const file of walk(ASSETS, ['.scss', '.tsx'])) {
      if (file.endsWith('.test.ts') || file.endsWith('.test.tsx')) continue;
      const source = readFileSync(file, 'utf8');
      for (const match of source.matchAll(/var\(\s*(--[a-zA-Z0-9_-]+)\s*(,)?/g)) {
        const [, name, fallback] = match;
        if (defined.has(name)) continue;
        if (fallback && KNOWN_UNDEFINED_WITH_FALLBACK.has(name)) continue;
        const line = source.slice(0, match.index).split('\n').length;
        offenders.push(
          `${file.replace(`${ASSETS}/`, '')}:${line} ${name}${fallback ? ' (with fallback)' : ''}`,
        );
      }
    }

    expect(
      offenders,
      'undefined custom properties; define them in assets/legacy-tokens.scss or use a canonical token',
    ).toEqual([]);
  });

  it('the allow-list only names properties that are really still undefined', () => {
    const defined = definedProperties();
    const stale = [...KNOWN_UNDEFINED_WITH_FALLBACK].filter((name) => defined.has(name));
    expect(stale, 'now defined: remove from KNOWN_UNDEFINED_WITH_FALLBACK').toEqual([]);
  });
});
