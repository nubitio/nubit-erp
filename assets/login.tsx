import React from 'react';
import ReactDOM from 'react-dom/client';

import './login.scss';
import { LoginPage } from '@features/auth/pages/LoginPage';
import { ErrorBoundary } from '@shared/components';

window.addEventListener('load', () => {
  const root = ReactDOM.createRoot(document.getElementById('root') as HTMLElement);
  root.render(
    <React.StrictMode>
      <ErrorBoundary>
        <LoginPage />
      </ErrorBoundary>
    </React.StrictMode>,
  );
});
