export type SunatStage = 'accepted' | 'rejected' | 'observed' | 'pending' | 'sent' | 'error';

/** Mirrors the existing ERP status ranges; replacement is only valid for 2000–3999. */
export function sunatStatus(value?: string | number | null) {
  const code = String(value ?? '').trim();
  const normalized = code.toLowerCase();
  const numeric = /^\d+$/.test(code) ? Number(code) : null;
  let stage: SunatStage = 'error';
  if (code === '0' || ['accepted', 'aceptado'].includes(normalized)) stage = 'accepted';
  else if (
    (numeric !== null && numeric >= 2000 && numeric < 4000) ||
    ['rejected', 'rechazado'].includes(normalized)
  )
    stage = 'rejected';
  else if ((numeric !== null && numeric >= 4000) || ['observed', 'observado'].includes(normalized))
    stage = 'observed';
  else if (['sent', 'enviado'].includes(normalized)) stage = 'sent';
  else if (!code || ['pending', 'pendiente', 'null'].includes(normalized)) stage = 'pending';
  const labels: Record<SunatStage, string> = {
    accepted: 'Aceptada por SUNAT',
    rejected: 'Rechazada por SUNAT',
    observed: 'Con observaciones',
    pending: 'Pendiente de envío',
    sent: 'Enviada, pendiente de respuesta',
    error: 'Error de envío',
  };
  return {
    stage,
    code,
    label: labels[stage],
    canReplace: numeric !== null && numeric >= 2000 && numeric < 4000,
    canSend: stage === 'pending' || stage === 'error',
  };
}
