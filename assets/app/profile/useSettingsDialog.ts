import { useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { parseSection, type ProfileSection } from './profileNav';

/** `?settings=<section>` on any page opens the settings dialog there, so it can be linked to. */
export const SETTINGS_PARAM = 'settings';

export function useSettingsDialog() {
  const [params, setParams] = useSearchParams();
  const raw = params.get(SETTINGS_PARAM);

  const write = useCallback(
    (section: ProfileSection | null, replace: boolean) =>
      setParams(
        (previous) => {
          const next = new URLSearchParams(previous);
          if (section === null) next.delete(SETTINGS_PARAM);
          else next.set(SETTINGS_PARAM, section);
          return next;
        },
        { replace },
      ),
    [setParams],
  );

  return {
    isOpen: raw !== null,
    section: parseSection(raw ?? undefined),
    /** Opens as a new history entry, so Back closes it. */
    open: useCallback((section: ProfileSection = 'account') => write(section, false), [write]),
    /** Switching section inside the dialog does not pile up history. */
    select: useCallback((section: ProfileSection) => write(section, true), [write]),
    close: useCallback(() => write(null, false), [write]),
  };
}
