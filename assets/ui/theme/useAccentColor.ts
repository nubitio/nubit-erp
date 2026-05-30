import { useCallback, useEffect, useState } from 'react';

// ── Preset palette ─────────────────────────────────────────────────────────────
// Curated enterprise-grade colors. Each has sufficient contrast on both
// white (light mode) and dark surfaces (dark mode) at WCAG AA level.

export interface AccentPreset {
  label: string;
  value: string;
}

export const ACCENT_PRESETS: AccentPreset[] = [
  { label: 'Blue',   value: '#2563eb' },
  { label: 'Indigo', value: '#4f46e5' },
  { label: 'Violet', value: '#7c3aed' },
  { label: 'Teal',   value: '#0d9488' },
  { label: 'Rose',   value: '#e11d48' },
  { label: 'Amber',  value: '#d97706' },
  { label: 'Slate',  value: '#475569' },
];

const STORAGE_KEY = 'app-accent-color';
const DEFAULT_ACCENT = '#2563eb';

// Apply a color by setting it as an inline style on <html>.
// Inline styles override any stylesheet rule — including the theme CSS file —
// so this reliably overrides the theme's --accent-color for all derived tokens.
function applyAccent(color: string): void {
  document.documentElement.style.setProperty('--accent-color', color);
}

export function useAccentColor() {
  const [accent, setAccent] = useState<string>(() => {
    return localStorage.getItem(STORAGE_KEY) ?? DEFAULT_ACCENT;
  });

  // Apply on mount and whenever the value changes.
  useEffect(() => {
    applyAccent(accent);
  }, [accent]);

  const changeAccent = useCallback((color: string) => {
    localStorage.setItem(STORAGE_KEY, color);
    setAccent(color);
  }, []);

  return { accent, changeAccent, presets: ACCENT_PRESETS };
}
