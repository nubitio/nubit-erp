import i18next from 'i18next';
import { coreTranslationsEs } from './coreTranslations';

export function initCoreI18n(): void {
  i18next.addResourceBundle('es', 'core', coreTranslationsEs, true, false);
}
