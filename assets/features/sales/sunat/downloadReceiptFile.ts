import { httpClient } from '@shared/api/HttpClient';

export async function downloadReceiptFile(url: string, filename: string, kind: 'xml' | 'cdr') {
  const { data } = await httpClient.get<Blob>(url, { responseType: 'blob' });
  if (!data.size || /html|json/.test(data.type))
    throw new Error('El archivo todavía no está disponible.');
  if (kind === 'cdr') {
    const bytes = new Uint8Array(await data.slice(0, 2).arrayBuffer());
    if (bytes[0] !== 80 || bytes[1] !== 75)
      throw new Error('La respuesta no es un archivo CDR ZIP.');
  } else if (!/^\s*<\?xml|^\s*<(?:\w+:)?(?:Invoice|CreditNote|DebitNote)/.test(await data.text())) {
    throw new Error('La respuesta no es un comprobante XML.');
  }
  const objectUrl = URL.createObjectURL(data);
  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(objectUrl);
}
