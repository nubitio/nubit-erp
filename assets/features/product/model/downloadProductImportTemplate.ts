import { downloadTemplate } from '@features/setup/downloadTemplate';

export function downloadProductImportTemplate(): Promise<void> {
  return downloadTemplate('/api/v1/products/import/template', 'plantilla-productos.xlsx');
}
