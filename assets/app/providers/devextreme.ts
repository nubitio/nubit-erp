import config from 'devextreme/core/config';
import { loadMessages, locale } from 'devextreme/localization';
import esMessages from 'devextreme/localization/messages/es.json';

let isDevExtremeConfigured = false;

export function configureDevExtreme() {
  if (isDevExtremeConfigured) {
    return;
  }

  loadMessages(esMessages);
  locale('es-PE');
  config({
    defaultCurrency: 'PEN',
  });

  isDevExtremeConfigured = true;
}
