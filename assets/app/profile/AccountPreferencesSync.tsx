import { useEffect, useRef } from 'react';
import { useDensity, useTheme } from '@nubitio/ui';
import { profileService, type ThemeMode } from './profileService';
import { changesToSave, planAdoption, themeSteps, type LocalPreferences } from './preferencesSync';
import { useAcceptProfile, useProfile } from './useProfile';

// If the theme never reaches what the account asked for (the library changed its cycle), stop
// waiting and carry on rather than never saving anything again.
const MAX_SETTLE_ATTEMPTS = 3;

/**
 * Keeps density and theme on the account, so they follow the person to another browser.
 * Renders nothing; mounted once inside the signed-in shell.
 *
 * Phases: wait for the account's values, apply them here (or offer this browser's where the account
 * has none), and from then on save what the person changes.
 */
export const AccountPreferencesSync = () => {
  const { data: profile } = useProfile();
  const accept = useAcceptProfile();
  const { density, toggleDensity } = useDensity();
  const { mode, switchTheme } = useTheme();

  const phase = useRef<'waiting' | 'applying' | 'tracking'>('waiting');
  const wanted = useRef<Partial<LocalPreferences>>({});
  const attempts = useRef(0);

  useEffect(() => {
    if (!profile) return;
    const local: LocalPreferences = { density, theme: mode as ThemeMode };
    const save = (preferences: Partial<LocalPreferences>) =>
      void profileService
        .update({ preferences })
        .then((response) => accept(response.data))
        .catch(() => undefined);

    if (phase.current === 'waiting') {
      const plan = planAdoption(profile.preferences, local);
      wanted.current = plan.apply;
      phase.current = 'applying';
      if (plan.apply.density) toggleDensity();
      // switchTheme advances with a functional update, so several calls in a row all count.
      if (plan.apply.theme) {
        for (let step = 0; step < themeSteps(local.theme, plan.apply.theme); step++) switchTheme();
      }
      if (Object.keys(plan.offer).length > 0) save(plan.offer);
      return;
    }

    if (phase.current === 'applying') {
      const target = wanted.current;
      const settled =
        (!target.density || target.density === density) && (!target.theme || target.theme === mode);
      attempts.current += 1;
      if (!settled && attempts.current <= MAX_SETTLE_ATTEMPTS) return;
      phase.current = 'tracking';
    }

    const changes = changesToSave(local, profile.preferences);
    if (Object.keys(changes).length > 0) save(changes);
    // `accept` and the toggles are fresh closures each render; what this reacts to is the data.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [profile, density, mode]);

  return null;
};
