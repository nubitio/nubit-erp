export function isTenantShellNavigation(context: {
  request: { mode: string; method?: string };
  url: { pathname: string };
}): boolean;

export function shellCacheKeyWillBeUsed(context: { request: { url: string } }): string;
