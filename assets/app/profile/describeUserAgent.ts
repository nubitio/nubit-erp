/** "Chrome en macOS" from a raw User-Agent; good enough to tell one's own devices apart. */
export function describeUserAgent(userAgent: string | null): string {
  if (!userAgent) return 'Dispositivo desconocido';

  // Order matters: Edge and Opera also say "Chrome", Chrome also says "Safari".
  const browser = /Edg\//.test(userAgent)
    ? 'Edge'
    : /OPR\/|Opera/.test(userAgent)
      ? 'Opera'
      : /Firefox\//.test(userAgent)
        ? 'Firefox'
        : /Chrome\/|CriOS\//.test(userAgent)
          ? 'Chrome'
          : /Safari\//.test(userAgent)
            ? 'Safari'
            : null;

  // iPhone UAs contain "like Mac OS X", so mobile systems are tested first.
  const system = /Android/.test(userAgent)
    ? 'Android'
    : /iPhone|iPad|iPod/.test(userAgent)
      ? 'iOS'
      : /Windows/.test(userAgent)
        ? 'Windows'
        : /Mac OS X|Macintosh/.test(userAgent)
          ? 'macOS'
          : /Linux/.test(userAgent)
            ? 'Linux'
            : null;

  if (browser && system) return `${browser} en ${system}`;
  return browser ?? system ?? 'Dispositivo desconocido';
}
