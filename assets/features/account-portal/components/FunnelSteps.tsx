interface FunnelStepsProps {
  current: 1 | 2 | 3 | 4;
}

const STEPS = [
  { id: 1, label: 'Cuenta' },
  { id: 2, label: 'Verificación' },
  { id: 3, label: 'Empresa' },
  { id: 4, label: 'Listo' },
] as const;

export function FunnelSteps({ current }: FunnelStepsProps) {
  return (
    <nav className="ap-funnel ap-funnel--compact" aria-label="Progreso de registro">
      <ol className="ap-funnel__list">
        {STEPS.map((step) => {
          const state = step.id < current ? 'done' : step.id === current ? 'current' : 'upcoming';
          return (
            <li key={step.id} className={`ap-funnel__step ap-funnel__step--${state}`}>
              <span className="ap-funnel__marker" aria-hidden="true">
                {state === 'done' ? <i className="ph ph-check" /> : step.id}
              </span>
              <span className="ap-funnel__label">{step.label}</span>
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
