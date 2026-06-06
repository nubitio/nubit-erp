import type { CrudViewMode, CrudViewModeConfig } from '../crud/ResourceConfig';

export interface ResolvedViewMode {
  mode: CrudViewMode;
  drawerWidth: number | string;
  drawerSide: 'right' | 'left';
}

const DEFAULTS: ResolvedViewMode = {
  mode: 'dialog',
  drawerWidth: 'min(720px, 60vw)',
  drawerSide: 'right',
};

/**
 * Normalises the `viewMode` field of `ResourceConfig` into a fully-resolved
 * object with defaults applied. Accepts the shorthand string form
 * (`'dialog' | 'drawer' | 'page'`) or the full config object.
 */
export function resolveViewMode(
  raw: CrudViewMode | CrudViewModeConfig | undefined,
): ResolvedViewMode {
  if (raw == null) return DEFAULTS;
  if (typeof raw === 'string') {
    return { ...DEFAULTS, mode: raw };
  }
  return {
    mode: raw.mode,
    drawerWidth: raw.drawerWidth ?? DEFAULTS.drawerWidth,
    drawerSide: raw.drawerSide ?? DEFAULTS.drawerSide,
  };
}
