import { currentTheme as currentVizTheme, refreshTheme } from 'devextreme/viz/themes';
import { current as getCurrentDXTheme } from 'devextreme/ui/themes';
import React, { useCallback, useEffect, useMemo, useState } from 'react';

const themes = ['light', 'dark'] as const;
const themeModes = ['light', 'dark', 'auto'] as const;
const storageKey = 'app-theme';
const themePrefix = 'app-theme-';

export type Theme = (typeof themes)[number];
export type ThemeMode = (typeof themeModes)[number];

function getNextThemeMode(mode?: ThemeMode): ThemeMode {
  return themeModes[themeModes.indexOf(mode as ThemeMode) + 1] || themeModes[0];
}

function getCurrentThemeMode(): ThemeMode {
  const persisted = window.localStorage[storageKey] as ThemeMode | undefined;
  if (persisted && themeModes.includes(persisted)) {
    return persisted;
  }
  return 'auto';
}

function resolveTheme(mode: ThemeMode): Theme {
  if (mode !== 'auto') {
    return mode;
  }
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function setThemeToDOM(theme: Theme): Promise<void> {
  return new Promise((resolve) => {
    const id = 'theme-link';
    let link = document.getElementById(id) as HTMLLinkElement | null;

    const href = `/themes/${themePrefix}${theme}.css`;

    if (link) {
      if (link.href.endsWith(href)) return resolve(); // already loaded
      link.href = href;
    } else {
      link = document.createElement('link');
      link.id = id;
      link.rel = 'stylesheet';
      link.href = href;
      document.head.appendChild(link);
    }

    link.onload = () => resolve();
  });
}

async function setAppTheme(theme: Theme) {
  await setThemeToDOM(theme);

  const regexTheme = new RegExp(`\\.(${themes.join('|')})`, 'g');
  currentVizTheme(currentVizTheme().replace(regexTheme, `.${theme}`));
  refreshTheme();
}

function toggleTheme(currentMode: ThemeMode): ThemeMode {
  const newMode = getNextThemeMode(currentMode);
  window.localStorage[storageKey] = newMode;
  return newMode;
}

export function useThemeContext() {
  const [mode, setMode] = useState<ThemeMode>(getCurrentThemeMode());
  const [isLoaded, setIsLoaded] = useState(false);
  const theme = useMemo(() => resolveTheme(mode), [mode]);

  useEffect(() => {
    setAppTheme(theme).then(() => setIsLoaded(true));
  }, [theme]);

  useEffect(() => {
    if (!isLoaded) {
      return;
    }

    const metaThemeColor = document.querySelector('meta[name="theme-color"]');
    if (metaThemeColor) {
      const baseBgColor = getComputedStyle(document.body).getPropertyValue('--base-bg').trim();
      metaThemeColor.setAttribute('content', baseBgColor || (theme === 'dark' ? '#000000' : '#ffffff'));
    }
  }, [theme, isLoaded]);

  useEffect(() => {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const applyAutoTheme = () => {
      if (mode === 'auto') {
        void setAppTheme(resolveTheme('auto'));
      }
    };
    mediaQuery.addEventListener('change', applyAutoTheme);
    return () => {
      mediaQuery.removeEventListener('change', applyAutoTheme);
    };
  }, [mode]);

  const switchTheme = useCallback(() => {
    setMode((currentMode) => toggleTheme(currentMode));
  }, []);

  const isFluent = useCallback(() => {
    return getCurrentDXTheme().includes('fluent');
  }, []);

  return useMemo(
    () => ({ theme, mode, switchTheme, isLoaded, isFluent }),
    [theme, mode, switchTheme, isLoaded, isFluent],
  );
}

export const ThemeContext = React.createContext<ReturnType<typeof useThemeContext> | null>(null);
