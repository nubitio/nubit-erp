/**
 * URL that opens the tenant app's support-session bridge for a one-time token.
 *
 * The token travels in the URL fragment (`/impersonate#token=…`), never in the path or the
 * query string (ADR-020, D3): a fragment is never sent to the server, so the token cannot show
 * up in an access log or a proxy. The bridge page reads it once and removes it from the URL.
 */
export function buildImpersonationUrl(
  location: Pick<Location, 'protocol' | 'port'>,
  primaryDomain: string,
  token: string,
): string {
  const port = location.port ? `:${location.port}` : '';
  return `${location.protocol}//${primaryDomain}${port}/impersonate#token=${encodeURIComponent(token)}`;
}
