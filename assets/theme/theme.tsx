import { currentTheme as currentVizTheme, refreshTheme } from 'devextreme/viz/themes';
import { current as getCurrentDXTheme } from 'devextreme/ui/themes';
import React, { useCallback, useEffect, useMemo, useState } from 'react';

const themes = ['light', 'dark'] as const;
const storageKey = 'app-theme';
const themePrefix = 'app-theme-';

export type Theme = (typeof themes)[number];

function getNextTheme(theme?: Theme): Theme {
  return themes[themes.indexOf(theme as Theme) + 1] || themes[0];
}

function getCurrentTheme(): Theme {
  return (window.localStorage[storageKey] as Theme) || 'light';
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

function toggleTheme(currentTheme: Theme): Theme {
  const newTheme = getNextTheme(currentTheme);
  window.localStorage[storageKey] = newTheme;
  return newTheme;
}

export function useThemeContext() {
  const [theme, setTheme] = useState(getCurrentTheme());
  const [isLoaded, setIsLoaded] = useState(false);

  useEffect(() => {
    setThemeToDOM(theme).then(() => setIsLoaded(true));
  }, []);

  useEffect(() => {
    if (isLoaded) {
      setAppTheme(theme);
      const metaThemeColor = document.querySelector('meta[name="theme-color"]');
      if (metaThemeColor) {
        const baseBgColor = getComputedStyle(document.body).getPropertyValue('--base-bg').trim();
        metaThemeColor.setAttribute(
          'content',
          baseBgColor || (theme === 'dark' ? '#000000' : '#ffffff'),
        );
      }
    }
  }, [theme, isLoaded]);

  const switchTheme = useCallback(() => {
    setTheme((currentTheme) => toggleTheme(currentTheme));
  }, []);

  const isFluent = useCallback(() => {
    return getCurrentDXTheme().includes('fluent');
  }, []);

  return useMemo(
    () => ({ theme, switchTheme, isLoaded, isFluent }),
    [theme, switchTheme, isLoaded, isFluent],
  );
}

export const ThemeContext = React.createContext<ReturnType<typeof useThemeContext> | null>(null);
