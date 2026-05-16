import React, { useCallback, useContext } from 'react';

import { ThemeContext } from '@theme/theme';

export const ThemeSwitcher = () => {
  const themeContext = useContext(ThemeContext);
  const mode = themeContext?.mode ?? 'light';
  const iconByMode = {
    light: 'ph ph-sun',
    dark: 'ph ph-moon',
    auto: 'ph ph-desktop',
  } as const;
  const hintByMode = {
    light: 'Tema: Claro',
    dark: 'Tema: Oscuro',
    auto: 'Tema: Sistema',
  } as const;

  const onButtonClick = useCallback(() => {
    themeContext?.switchTheme();
  }, [themeContext]);

  return (
    <div>
      <button
        className="app-icon-button theme-button"
        type="button"
        aria-label={hintByMode[mode]}
        title={hintByMode[mode]}
        onClick={onButtonClick}
      >
        <i className={iconByMode[mode]} aria-hidden="true" />
      </button>
    </div>
  );
};
