const LOGO = '/assets/images/nubit-icon.png';

const FEATURES = [
  'POS y facturación electrónica SUNAT',
  'Inventario, compras y tesorería',
  'Contabilidad y planilla',
] as const;

interface AccountPortalBrandProps {
  headline?: string;
  description?: string;
}

export function AccountPortalBrand({
  headline = 'Gestiona tu negocio\nen un solo lugar.',
  description = 'Vende, factura a SUNAT, controla stock, compras, finanzas y planilla en un solo sistema.',
}: AccountPortalBrandProps) {
  const titleLines = headline.split('\n');

  return (
    <aside className="ap-brand" aria-hidden="true">
      <div className="ap-brand__blobs">
        <span className="ap-brand__blob ap-brand__blob--1" />
        <span className="ap-brand__blob ap-brand__blob--2" />
        <span className="ap-brand__blob ap-brand__blob--3" />
      </div>

      <div className="ap-brand__content">
        <div className="ap-brand__logo-wrap">
          <span className="ap-brand__brand">
            <img src={LOGO} className="ap-brand__logo" alt="" />
            <span>Nubit ERP</span>
          </span>
        </div>

        <div className="ap-brand__illustration">
          <img src="/assets/images/illustration.svg" alt="" />
        </div>

        <div className="ap-brand__text">
          <h1>
            {titleLines.map((line, index) => (
              <span key={line}>
                {line}
                {index < titleLines.length - 1 ? <br /> : null}
              </span>
            ))}
          </h1>
          <p>{description}</p>
        </div>

        <ul className="ap-brand__features">
          {FEATURES.map((feature) => (
            <li key={feature}>
              <i className="ph ph-check-circle" aria-hidden="true" />
              {feature}
            </li>
          ))}
        </ul>
      </div>
    </aside>
  );
}
