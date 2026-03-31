import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import es from './locales/es.json';
import { initCoreI18n } from '../core/i18n';

i18n.use(initReactI18next).init({
  lng: 'es',
  fallbackLng: 'es',
  resources: { es: { translation: es } },
  interpolation: { escapeValue: false },
});

// Register core component translations (grid, form, dialog labels)
initCoreI18n();

export default i18n;
