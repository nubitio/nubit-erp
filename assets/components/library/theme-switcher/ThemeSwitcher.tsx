import React, { useCallback, useContext } from 'react';
import Button from 'devextreme-react/button';
import { ThemeContext } from '../../../theme/theme';

export const ThemeSwitcher = () => {
  const themeContext = useContext(ThemeContext);
  const mode = themeContext?.mode ?? 'light';
  const labelByMode = {
    light: 'CL',
    dark: 'OS',
    auto: 'SO',
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
      <Button
        className="theme-button"
        stylingMode="text"
        text={labelByMode[mode]}
        hint={hintByMode[mode]}
        elementAttr={{ 'aria-label': hintByMode[mode] }}
        onClick={onButtonClick}
      />
    </div>
  );
};
