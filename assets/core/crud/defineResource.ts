import type { ResourceConfig } from './ResourceConfig';

export function defineResource<T extends Record<string, unknown> = Record<string, unknown>>(
  config: ResourceConfig<T>,
): ResourceConfig<T> {
  return config;
}
