import React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, cleanup, act } from '@testing-library/react';
import { ThemeProvider, useTheme } from './ThemeProvider';
import { ThemeSwitcher } from './ThemeSwitcher';

beforeEach(() => {
  // Silence CSS fetch side-effects from ThemeProvider's setThemeToDOM
  vi.stubGlobal('fetch', vi.fn(() => Promise.resolve(new Response(null, { status: 200 }))));
});

afterEach(() => {
  cleanup();
  localStorage.clear();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

// ── matchMedia stub ───────────────────────────────────────────────────────────

function stubMatchMedia(preferssDark = false) {
  const listeners: Array<(e: MediaQueryListEvent) => void> = [];
  const mq = {
    matches: preferssDark,
    media: '',
    onchange: null,
    addEventListener: vi.fn((_, cb) => listeners.push(cb as (e: MediaQueryListEvent) => void)),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
  } as unknown as MediaQueryList;
  vi.stubGlobal('matchMedia', vi.fn(() => mq));
  return { mq, listeners };
}

// ── helpers ───────────────────────────────────────────────────────────────────

function CurrentMode() {
  const { mode, theme } = useTheme();
  return <span data-testid="mode">{mode}:{theme}</span>;
}

function SwitchButton() {
  const { switchTheme } = useTheme();
  return <button type="button" onClick={switchTheme}>switch</button>;
}

// ── initial state ─────────────────────────────────────────────────────────────

describe('ThemeProvider initial state', () => {
  it('defaults to auto mode when no localStorage key', () => {
    stubMatchMedia(false);
    render(
      <ThemeProvider>
        <CurrentMode />
      </ThemeProvider>,
    );
    expect(screen.getByTestId('mode').textContent).toMatch(/^auto:/);
  });

  it('resolves auto+light system preference to light theme', () => {
    stubMatchMedia(false);
    render(
      <ThemeProvider>
        <CurrentMode />
      </ThemeProvider>,
    );
    expect(screen.getByTestId('mode').textContent).toBe('auto:light');
  });

  it('resolves auto+dark system preference to dark theme', () => {
    stubMatchMedia(true);
    render(
      <ThemeProvider>
        <CurrentMode />
      </ThemeProvider>,
    );
    expect(screen.getByTestId('mode').textContent).toBe('auto:dark');
  });

  it('reads persisted mode from localStorage', () => {
    stubMatchMedia(false);
    localStorage.setItem('app-theme', 'dark');
    render(
      <ThemeProvider>
        <CurrentMode />
      </ThemeProvider>,
    );
    expect(screen.getByTestId('mode').textContent).toBe('dark:dark');
  });

  it('uses custom storageKey', () => {
    stubMatchMedia(false);
    localStorage.setItem('my-theme', 'dark');
    render(
      <ThemeProvider storageKey="my-theme">
        <CurrentMode />
      </ThemeProvider>,
    );
    expect(screen.getByTestId('mode').textContent).toBe('dark:dark');
  });

  it('falls back to auto when localStorage has an invalid value', () => {
    stubMatchMedia(false);
    localStorage.setItem('app-theme', 'invalid-mode');
    render(
      <ThemeProvider>
        <CurrentMode />
      </ThemeProvider>,
    );
    expect(screen.getByTestId('mode').textContent).toMatch(/^auto:/);
  });
});

// ── switchTheme cycling ───────────────────────────────────────────────────────

describe('ThemeProvider switchTheme', () => {
  it('cycles through auto → light → dark → auto', () => {
    stubMatchMedia(false);
    render(
      <ThemeProvider>
        <CurrentMode />
        <SwitchButton />
      </ThemeProvider>,
    );
    const modeEl = screen.getByTestId('mode');
    const btn = screen.getByText('switch');

    expect(modeEl.textContent).toMatch(/^auto:/);

    act(() => fireEvent.click(btn));
    expect(modeEl.textContent).toMatch(/^light:/);

    act(() => fireEvent.click(btn));
    expect(modeEl.textContent).toMatch(/^dark:/);

    act(() => fireEvent.click(btn));
    expect(modeEl.textContent).toMatch(/^auto:/);
  });

  it('persists the new mode to localStorage', () => {
    stubMatchMedia(false);
    render(
      <ThemeProvider storageKey="app-theme">
        <SwitchButton />
      </ThemeProvider>,
    );
    act(() => fireEvent.click(screen.getByText('switch')));
    expect(localStorage.getItem('app-theme')).toBe('light');
  });
});

// ── useTheme guard ────────────────────────────────────────────────────────────

describe('useTheme outside provider', () => {
  it('throws when used outside ThemeProvider', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});
    expect(() => render(<CurrentMode />)).toThrow('useTheme must be used inside ThemeProvider');
    spy.mockRestore();
  });
});

// ── ThemeSwitcher ─────────────────────────────────────────────────────────────

describe('ThemeSwitcher', () => {
  it('renders a button', () => {
    stubMatchMedia(false);
    render(
      <ThemeProvider>
        <ThemeSwitcher />
      </ThemeProvider>,
    );
    expect(screen.getByRole('button')).toBeDefined();
  });

  it('calls switchTheme when clicked', () => {
    stubMatchMedia(false);
    render(
      <ThemeProvider>
        <ThemeSwitcher />
        <CurrentMode />
      </ThemeProvider>,
    );
    const before = screen.getByTestId('mode').textContent;
    act(() => fireEvent.click(screen.getByRole('button')));
    expect(screen.getByTestId('mode').textContent).not.toBe(before);
  });

  it('applies overridden labels via labelByMode prop', () => {
    stubMatchMedia(false);
    localStorage.setItem('app-theme', 'light');
    render(
      <ThemeProvider>
        <ThemeSwitcher labelByMode={{ light: 'Tema: Claro' }} />
      </ThemeProvider>,
    );
    expect(screen.getByRole('button').getAttribute('aria-label')).toBe('Tema: Claro');
  });
});
