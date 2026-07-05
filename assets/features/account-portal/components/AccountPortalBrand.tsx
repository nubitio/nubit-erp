const LOGO = '/assets/images/efact-logo-dark.svg';

const FEATURES = [
  'Facturación electrónica SUNAT',
  'POS integrado multilocal',
  'Inventario en tiempo real',
] as const;

interface AccountPortalBrandProps {
  headline?: string;
  description?: string;
}

export function AccountPortalBrand({
  headline = 'Simplifica tu trabajo,\noptimiza tu negocio.',
  description = 'La plataforma de facturación electrónica más intuitiva para tu empresa.',
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
          <img src={LOGO} className="ap-brand__logo" alt="eFact" />
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