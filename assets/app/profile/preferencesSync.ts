import type { AccountPreferences, Density, ThemeMode } from './profileService';

export interface LocalPreferences {
  density: Density;
  theme: ThemeMode;
}

/**
 * First load of a session: what the account says wins, and what it has never said is filled in
 * from this browser. That order matters: if the account's defaults won, signing in on a new
 * device would silently replace a density the person had picked.
 */
export function planAdoption(
  server: AccountPreferences,
  local: LocalPreferences,
): { apply: Partial<LocalPreferences>; offer: Partial<LocalPreferences> } {
  const apply: Partial<LocalPreferences> = {};
  const offer: Partial<LocalPreferences> = {};

  if (server.density === null) offer.density = local.density;
  else if (server.density !== local.density) apply.density = server.density;

  if (server.theme === null) offer.theme = local.theme;
  else if (server.theme !== local.theme) apply.theme = server.theme;

  return { apply, offer };
}

/** After that, a change made on this browser is what gets saved. */
export function changesToSave(
  local: LocalPreferences,
  server: AccountPreferences,
): Partial<LocalPreferences> {
  const changes: Partial<LocalPreferences> = {};
  if (local.density !== server.density) changes.density = local.density;
  if (local.theme !== server.theme) changes.theme = local.theme;
  return changes;
}

/** The order `@nubitio/ui`'s `switchTheme` walks through (one step per call, wrapping around). */
export const THEME_CYCLE: readonly ThemeMode[] = ['light', 'dark', 'auto'];

/** How many `switchTheme()` calls it takes to get from one mode to another. */
export function themeSteps(from: ThemeMode, to: ThemeMode): number {
  const length = THEME_CYCLE.length;
  return (THEME_CYCLE.indexOf(to) - THEME_CYCLE.indexOf(from) + length) % length;
}
