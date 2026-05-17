import config from 'devextreme/core/config';
import { loadMessages, locale } from 'devextreme/localization';
import esMessages from 'devextreme/localization/messages/es.json';
import { registerPalette, currentPalette } from 'devextreme/viz/palette';

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

  registerPalette('brand', {
    // Multi-series: brand green first, then distinct complementary colors
    simpleSet: [
      '#0E7A50',
      '#3B82F6',
      '#F59E0B',
      '#8B5CF6',
      '#EC4899',
      '#06B6D4',
      '#84CC16',
      '#EF4444',
    ],
    // Traffic-light indicators: danger -> warning -> success
    indicatingSet: ['#EF4444', '#F59E0B', '#0E7A50'],
    // Gradient range (e.g. heatmaps): light -> dark green
    gradientSet: ['#6EE7B7', '#0E7A50'],
  });
  currentPalette('brand');

  isDevExtremeConfigured = true;
}
