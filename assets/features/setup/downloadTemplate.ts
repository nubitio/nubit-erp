import { httpClient } from '@shared/api/HttpClient';

/** Downloads a spreadsheet template through the authenticated transport; a plain link carries no Bearer token. */
export async function downloadTemplate(path: string, fileName: string): Promise<void> {
  const { data } = await httpClient.get<Blob>(path, { responseType: 'blob' });
  if (data.type.includes('text/html'))
    throw new Error('No se pudo descargar la plantilla. Intenta de nuevo.');
  const url = URL.createObjectURL(data);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = fileName;
  document.body.appendChild(anchor);
  try {
    anchor.click();
  } finally {
    anchor.remove();
    URL.revokeObjectURL(url);
  }
}
