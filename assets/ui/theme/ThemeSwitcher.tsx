import React from 'react';
import { useTheme } from './ThemeProvider';

const iconByMode = {
  light: 'ph ph-sun',
  dark: 'ph ph-moon',
  auto: 'ph ph-desktop',
} as const;

const labelByMode = {
  light: 'Theme: Light',
  dark: 'Theme: Dark',
  auto: 'Theme: System',
} as const;

export interface ThemeSwitcherProps {
  labelByMode?: Partial<Record<keyof typeof labelByMode, string>>;
}

export const ThemeSwitcher = ({ labelByMode: overrides }: ThemeSwitcherProps = {}) => {
  const { mode, switchTheme } = useTheme();
  const labels = { ...labelByMode, ...overrides };
  const label = labels[mode];

  return (
    <div>
      <button
        className="app-icon-button theme-button"
        type="button"
        aria-label={label}
        title={label}
        onClick={switchTheme}
      >
        <i className={iconByMode[mode]} aria-hidden="true" />
      </button>
    </div>
  );
};
