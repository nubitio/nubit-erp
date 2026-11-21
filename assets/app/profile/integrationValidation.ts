/** A webhook is only ever called over https, at an address that parses as one. */
export function validateWebhookUrl(value: string): string | null {
  const url = value.trim();
  if (url === '') return 'Indica la URL del endpoint.';
  let parsed: URL;
  try {
    parsed = new URL(url);
  } catch {
    return 'La URL no es válida.';
  }
  if (parsed.protocol !== 'https:') return 'La URL debe empezar con https://.';
  if (parsed.username !== '' || parsed.password !== '') {
    return 'La URL no puede incluir usuario ni contraseña.';
  }
  return null;
}

export function validateApiClientName(value: string): string | null {
  const name = value.trim();
  if (name === '') return 'Indica un nombre para reconocer la llave.';
  if (name.length > 120) return 'El nombre no puede superar 120 caracteres.';
  return null;
}
