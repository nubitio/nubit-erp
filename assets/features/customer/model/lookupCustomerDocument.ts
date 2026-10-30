import type { FormHandle } from '@nubitio/crud';
import { consultService } from '@features/consult';
import { SharedUtils } from '@shared/lib/SharedUtils';

type LookupForm = Pick<FormHandle, 'getFieldValue' | 'setFieldValue'> & {
  isCurrent?: () => boolean;
};
type Feedback = { tone: 'success' | 'warning' | 'danger'; message: string };

/** Enrich only the current document and preserve fields manually edited while the lookup runs. */
export async function lookupCustomerDocument(
  form: LookupForm | null,
): Promise<Feedback | undefined> {
  if (!form) return;
  const doc = String(form.getFieldValue('ruc') ?? '').trim();
  if (!/^\d{8}$|^\d{11}$/.test(doc))
    return {
      tone: 'warning',
      message: 'Ingresa un DNI de 8 dígitos o un RUC de 11 dígitos para consultar.',
    };
  const isRuc = doc.length === 11;
  if (isRuc && !SharedUtils.validateRUC(doc))
    return { tone: 'warning', message: 'El RUC ingresado no es válido.' };
  const originalName = form.getFieldValue('businessName');
  const originalAddress = form.getFieldValue('fiscalAddress');
  const isCurrent = () =>
    (form.isCurrent?.() ?? true) && String(form.getFieldValue('ruc') ?? '').trim() === doc;
  try {
    const { data } = await (isRuc
      ? consultService.consultRuc(doc, { errorPresentation: 'inline' })
      : consultService.consultDni(doc, { errorPresentation: 'inline' }));
    if (!isCurrent()) return;
    if (String(data.id) !== doc || !data.name?.trim())
      return { tone: 'warning', message: 'No se encontraron datos para este documento.' };
    if (isRuc && !data.active)
      return {
        tone: 'danger',
        message: 'El RUC consultado no se encuentra activo. Revisa los datos antes de continuar.',
      };
    let preserved = false;
    if (form.getFieldValue('businessName') === originalName)
      form.setFieldValue('businessName', data.name);
    else preserved = true;
    if (isRuc && data.address?.trim()) {
      if (form.getFieldValue('fiscalAddress') === originalAddress)
        form.setFieldValue('fiscalAddress', data.address);
      else preserved = true;
    }
    return preserved
      ? {
          tone: 'warning',
          message:
            'Consulta completada. Se conservaron los campos que editaste durante la consulta.',
        }
      : {
          tone: 'success',
          message: isRuc
            ? 'Encontrado en SUNAT · RUC activo. Datos completados.'
            : 'DNI encontrado. Nombre completado.',
        };
  } catch {
    if (!isCurrent()) return;
    return {
      tone: 'danger',
      message: `No se pudo consultar el ${isRuc ? 'RUC' : 'DNI'}. Intenta nuevamente.`,
    };
  }
}
