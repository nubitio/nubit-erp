import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';

/**
 * Parameters carried in the URL fragment: `/reset-password#token=…` (ADR-020, D3).
 *
 * A bearer secret must not travel in the path or the query string: Caddy writes the
 * full request URI to its access log, and so would any proxy in front of it. The
 * fragment is never sent to the server, so the server only ever sees `/reset-password`.
 *
 * A hash that starts with `/` is a legacy hash-router location (`#/reset-password`),
 * not a parameter list, and yields nothing.
 */
export function parseFragmentParams(hash: string): URLSearchParams {
  const raw = hash.startsWith('#') ? hash.slice(1) : hash;
  if (raw.startsWith('/')) return new URLSearchParams();
  return new URLSearchParams(raw);
}

/**
 * Reads secrets from the URL once, then removes them from the address bar and the
 * history entry so they never linger, get bookmarked or are shared by accident.
 *
 * The fragment is the canonical carrier. The query string is still read as a
 * fallback for hand-made links, and is removed the same way.
 *
 * Values are captured on the first render and stay stable afterwards; the URL is
 * scrubbed in an effect, so callers keep their token after it disappears from the URL.
 * Pass a module-level constant for `names`.
 */
export function useFragmentParams<K extends string>(names: readonly K[]): Record<K, string | null> {
  const location = useLocation();
  const navigate = useNavigate();

  const [values] = useState(() => {
    const fragment = parseFragmentParams(location.hash);
    const query = new URLSearchParams(location.search);
    return Object.fromEntries(
      names.map((name) => [name, fragment.get(name) ?? query.get(name)]),
    ) as Record<K, string | null>;
  });

  const key = names.join('|');
  const { pathname, search, hash } = location;
  useEffect(() => {
    const fragment = parseFragmentParams(hash);
    const query = new URLSearchParams(search);
    let found = false;
    for (const name of key.split('|')) {
      if (fragment.has(name)) {
        fragment.delete(name);
        found = true;
      }
      if (query.has(name)) {
        query.delete(name);
        found = true;
      }
    }
    if (!found) return;

    const nextSearch = query.toString();
    const nextHash = fragment.toString();
    navigate(
      {
        pathname,
        search: nextSearch ? `?${nextSearch}` : '',
        hash: nextHash ? `#${nextHash}` : '',
      },
      { replace: true },
    );
  }, [key, pathname, search, hash, navigate]);

  return values;
}
