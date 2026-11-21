import { SegmentedControl, useDensity, useTheme } from '@nubitio/ui';
import type { Density, ThemeMode } from '../profileService';
import { themeSteps } from '../preferencesSync';
import { SettingsGroup, SettingsRow } from '../settings/SettingsRow';

const DENSITY_OPTIONS: { value: Density; label: string }[] = [
  { value: 'normal', label: 'Normal' },
  { value: 'compact', label: 'Compacta' },
];

const THEME_OPTIONS: { value: ThemeMode; label: string }[] = [
  { value: 'light', label: 'Claro' },
  { value: 'dark', label: 'Oscuro' },
  { value: 'auto', label: 'Sistema' },
];

/**
 * Applies at once; saving on the account is `AccountPreferencesSync`'s job, so what is chosen here
 * follows the person to any browser.
 */
export const AppearanceSection = () => {
  const { density, toggleDensity } = useDensity();
  const { mode, switchTheme } = useTheme();

  const chooseTheme = (target: ThemeMode) => {
    // ui only steps to the next theme; several calls in a row each advance (functional update).
    for (let step = 0; step < themeSteps(mode as ThemeMode, target); step++) switchTheme();
  };

  return (
    <SettingsGroup>
      <SettingsRow
        label="Densidad de la interfaz"
        description="La compacta muestra más filas en pantalla."
      >
        <SegmentedControl
          ariaLabel="Densidad de la interfaz"
          options={DENSITY_OPTIONS}
          value={density}
          onChange={(value) => value !== density && toggleDensity()}
        />
      </SettingsRow>
      <SettingsRow label="Tema" description="«Sistema» sigue el modo claro u oscuro de tu equipo.">
        <SegmentedControl
          ariaLabel="Tema"
          options={THEME_OPTIONS}
          value={(mode as ThemeMode) ?? 'auto'}
          onChange={chooseTheme}
        />
      </SettingsRow>
    </SettingsGroup>
  );
};
