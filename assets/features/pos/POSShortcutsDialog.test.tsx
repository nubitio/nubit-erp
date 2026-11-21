import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { POSShortcutsDialog, posShortcutGroups } from './POSShortcutsDialog';

afterEach(cleanup);

describe('POSShortcutsDialog', () => {
  it('lists every shortcut the POS binds', () => {
    render(<POSShortcutsDialog open onClose={vi.fn()} />);
    // Testing Library collapses runs of spaces in the DOM text, so compare in that form.
    const shown = screen.getAllByText((_, el) => el?.tagName === 'KBD').map((el) => el.textContent);
    for (const keys of [
      'F2 · /',
      'F3',
      'Shift + F3',
      'F4',
      'Shift + F4',
      'F8',
      'F9 · Enter',
      '?',
    ]) {
      expect(shown.map((text) => text?.replace(/\s+/g, ' '))).toContain(keys);
    }
  });

  it('shows Ctrl+K only while the assistant is enabled', () => {
    const keysOf = (enabled: boolean) =>
      posShortcutGroups(enabled).flatMap((group) => group.items.map(([keys]) => keys));
    expect(keysOf(false)).not.toContain('Ctrl / ⌘ + K');
    expect(keysOf(true)).toContain('Ctrl / ⌘ + K');
  });

  it('uses each key combination once', () => {
    const keys = posShortcutGroups(true).flatMap((group) => group.items.map(([k]) => k));
    expect(new Set(keys).size).toBe(keys.length);
  });
});
