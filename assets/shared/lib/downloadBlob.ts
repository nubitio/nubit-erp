/** Saves a blob through a temporary link, the way a user-initiated download behaves. */
export function downloadBlob(blob: Blob, filename: string): void {
  const objectUrl = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(objectUrl);
}

/**
 * The file name a server suggests in `Content-Disposition`, or `fallback`.
 * Handles `filename="x.xlsx"`, unquoted `filename=x.xlsx` and RFC 5987 `filename*=UTF-8''x%20y.xlsx`
 * (which wins when both are present, as the RFC says).
 */
export function filenameFromDisposition(
  headers: Record<string, string> | undefined,
  fallback: string,
): string {
  const entry = Object.entries(headers ?? {}).find(
    ([k]) => k.toLowerCase() === 'content-disposition',
  );
  const value = entry?.[1];
  if (!value) return fallback;

  const extended = /filename\*\s*=\s*(?:[\w-]+)?'[^']*'([^;]+)/i.exec(value)?.[1];
  if (extended) {
    try {
      return decodeURIComponent(extended.trim());
    } catch {
      /* fall through to the plain form */
    }
  }

  const plain = /filename\s*=\s*(?:"([^"]*)"|([^;]+))/i.exec(value);
  const name = (plain?.[1] ?? plain?.[2])?.trim();
  return name || fallback;
}
