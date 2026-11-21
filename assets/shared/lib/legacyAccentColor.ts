/** Where `@nubitio/ui`'s accent picker saved the user's choice. */
const ACCENT_STORAGE_KEY = 'app-accent-color';

/**
 * The accent picker was removed: the ERP uses one accent. A colour a user picked earlier would
 * otherwise sit in their browser, and apply again if any component asked for it. Drop it once.
 */
export function clearLegacyAccentColor(): void {
  try {
    window.localStorage.removeItem(ACCENT_STORAGE_KEY);
  } catch {
    // Storage can be blocked (private window, site data off); there is nothing to clean then.
  }
  document.documentElement.style.removeProperty('--accent-color');
}
