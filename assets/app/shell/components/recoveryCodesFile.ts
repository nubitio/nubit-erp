export interface RecoveryCodesFile {
  filename: string;
  content: string;
}

/** The text a person keeps (or prints) so they can still sign in if they lose their phone. */
export function recoveryCodesFile({
  username,
  codes,
  issuedAt,
}: {
  username: string;
  codes: string[];
  issuedAt: Date;
}): RecoveryCodesFile {
  const safeName = username.replace(/[^\w.-]+/g, '_') || 'usuario';
  const lines = [
    'Nubit ERP — Códigos de recuperación',
    `Usuario: ${username}`,
    `Generados: ${issuedAt.toLocaleDateString('es-PE')}`,
    '',
    'Cada código sirve una sola vez si pierdes tu teléfono.',
    'Guárdalos en un lugar seguro: quien los tenga puede ingresar a tu cuenta.',
    '',
    ...codes,
    '',
  ];

  return {
    filename: `nubit-erp-codigos-recuperacion-${safeName}.txt`,
    content: lines.join('\n'),
  };
}
