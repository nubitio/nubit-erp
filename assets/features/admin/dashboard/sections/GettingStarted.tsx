import { useNavigate } from 'react-router-dom';

const STEPS: Array<{ label: string; to: string }> = [
  { label: 'Empresa', to: '/config/company' },
  { label: 'Series SUNAT', to: '/config/series' },
  { label: 'Primer producto', to: '/catalog/products' },
  { label: 'Primera venta', to: '/pos' },
];

/** Onboarding shortcuts — only rendered while tenant setup is incomplete. */
export function GettingStarted() {
  const navigate = useNavigate();

  return (
    <section className="efact-getting-started" aria-label="Primeros pasos">
      <span className="efact-getting-started__label">Primeros pasos</span>
      <div className="efact-getting-started__chips">
        {STEPS.map((step) => (
          <button key={step.to} type="button" onClick={() => navigate(step.to)}>
            {step.label}
          </button>
        ))}
      </div>
    </section>
  );
}
