import { httpClient } from '@shared/api/HttpClient';

/** Use the shared authenticated transport; a plain link cannot carry Bearer auth. */
export async function downloadProductImportTemplate(): Promise<void> {
  const { data } = await httpClient.get<Blob>('/api/v1/products/import/template', {
    responseType: 'blob',
  });
  if (data.type.includes('text/html'))
    throw new Error('No se pudo descargar la plantilla. Intenta de nuevo.');
  const url = URL.createObjectURL(data);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = 'plantilla-productos.xlsx';
  document.body.appendChild(anchor);
  try {
    anchor.click();
  } finally {
    anchor.remove();
    URL.revokeObjectURL(url);
  }
}
