import { Suspense, type ReactNode } from 'react';
import './settingsHub.scss';

const CrudLoading = () => (
  <div className="settings-loading">
    <i className="ph ph-circle-notch settings-loading__icon" aria-hidden="true" />
    Cargando…
  </div>
);

export const SettingsCrudPanel = ({ children }: { children: ReactNode }) => (
  <div className="settings-crud-panel" data-testid="settings-crud-panel">
    <Suspense fallback={<CrudLoading />}>{children}</Suspense>
  </div>
);
