import { currentTheme as currentVizTheme, refreshTheme } from 'devextreme/viz/themes';
import { current as getCurrentDXTheme } from 'devextreme/ui/themes';

export function syncDevExtremeTheme(themes: readonly string[], theme: string) {
  const regexTheme = new RegExp(`\\.(${themes.join('|')})`, 'g');
  currentVizTheme(currentVizTheme().replace(regexTheme, `.${theme}`));
  refreshTheme();
}

export function isDevExtremeFluentTheme() {
  return getCurrentDXTheme().includes('fluent');
}
