import { useTranslation } from 'react-i18next';
import type { CoreTranslationKeys } from './CoreTranslationKeys';

export function useCoreTranslation() {
  const { t } = useTranslation('core');
  return {
    t: (key: keyof CoreTranslationKeys, options?: Record<string, unknown>) =>
      t(key, options) as string,
  };
}
