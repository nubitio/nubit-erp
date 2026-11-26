import { useMemo } from 'react';
import './OnboardingHelp.scss';

type OnboardingHelpProps = {
  step: 'Welcome' | 'Company' | 'SUNAT' | 'Branch' | 'Warehouse' | 'Done';
};

const FAQ: Record<OnboardingHelpProps['step'], Array<{ q: string; a: string }>> = {
  Welcome: [
    {
      q: '¿Qué giro elijo?',
      a: 'Retail si vendes productos de mostrador. Botica si manejas lotes o vencimientos. Puedes cambiar inventario después.',
    },
  ],
  Company: [
    {
      q: '¿Qué RUC pongo?',
      a: 'El RUC de 11 dígitos de tu empresa. Usa Consultar SUNAT para completar razón social y domicilio fiscal.',
    },
  ],
  SUNAT: [
    {
      q: '¿Qué es el certificado?',
      a: 'El archivo .p12, .pfx o PEM que SUNAT te da para firmar boletas y facturas. Si no lo tienes ahora, puedes configurarlo después en Ajustes. No se sube por el chat.',
    },
  ],
  Branch: [
    {
      q: '¿Qué es la serie F001 / B001?',
      a: 'F001 es la serie de facturas y B001 la de boletas. El sistema las crea para tu local. Puedes agregar más series después.',
    },
  ],
  Warehouse: [
    {
      q: '¿Para qué el almacén?',
      a: 'Es el lugar donde vive el stock. En tienda única no lo vas a ver todo el tiempo; queda vinculado a tu local.',
    },
  ],
  Done: [
    {
      q: '¿Y ahora?',
      a: 'Entra al dashboard y registra tu primera venta desde el POS. Desde ahí recorres inventario, compras, finanzas y planilla.',
    },
  ],
};

export function OnboardingHelp({ step }: OnboardingHelpProps) {
  const items = useMemo(() => FAQ[step], [step]);

  return (
    <aside className="onboarding-help" aria-label="Ayuda de configuración">
      <strong className="onboarding-help__title">Ayuda</strong>
      {items.map((item) => (
        <details key={item.q} className="onboarding-help__item">
          <summary>{item.q}</summary>
          <p>{item.a}</p>
        </details>
      ))}
    </aside>
  );
}
