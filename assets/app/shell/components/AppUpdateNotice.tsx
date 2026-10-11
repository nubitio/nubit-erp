import React, { useState } from 'react';
import { applyAppUpdate, useAppUpdate } from '@offline/appUpdate';
import './AppUpdateNotice.scss';

export function AppUpdateNotice() {
  const update = useAppUpdate();
  const [dismissed, setDismissed] = useState(false);
  if (!update.available || (dismissed && !update.required)) return null;
  return (
    <aside className="app-update-notice" role="status" aria-live="polite">
      <div>
        <strong>
          {update.required ? 'Actualización necesaria' : 'Hay una nueva versión disponible'}
        </strong>
        <p>
          {update.busy > 0
            ? 'Termina el cobro o la sincronización en curso para actualizar.'
            : update.required
              ? 'Actualiza antes de iniciar nuevas operaciones. Las ventas pendientes se conservarán.'
              : 'Puedes actualizar cuando estés listo. Las ventas pendientes se conservarán.'}
        </p>
        {update.error && <p role="alert">{update.error}</p>}
      </div>
      <button
        type="button"
        disabled={update.busy > 0 || update.updating}
        onClick={() => void applyAppUpdate()}
      >
        {update.updating ? 'Actualizando…' : 'Actualizar ahora'}
      </button>
      {!update.required && !update.updating && (
        <button
          type="button"
          className="app-update-notice__later"
          onClick={() => setDismissed(true)}
        >
          Más tarde
        </button>
      )}
    </aside>
  );
}
