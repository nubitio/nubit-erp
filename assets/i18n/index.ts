import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import es from './locales/es.json';
import en from './locales/en.json';
import { initCoreI18n } from '../core/i18n';

void i18n.use(initReactI18next).init({
  lng: (typeof navigator !== 'undefined' && navigator.language?.split('-')[0]) || 'es',
  fallbackLng: 'es',
  supportedLngs: ['es', 'en'],
  resources: { es: { translation: es }, en: { translation: en } },
  interpolation: { escapeValue: false },
}).then(() => {
  // Register core component translations (grid, form, dialog labels)
  initCoreI18n();
});

export default i18n;
