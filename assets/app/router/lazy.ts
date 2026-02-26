import { lazy, type ComponentType } from 'react';

export const lazyNamed = <TModule, TKey extends keyof TModule>(
  importer: () => Promise<TModule>,
  exportName: TKey,
) =>
  lazy(async () => {
    const mod = await importer();
    return { default: mod[exportName] as ComponentType<any> };
  });
