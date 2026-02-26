import React from 'react';
import ReactDOM from 'react-dom/client';

import './login.scss';
import { LoginPage } from '@features/auth/pages/LoginPage';

window.addEventListener('load', () => {
  const root = ReactDOM.createRoot(document.getElementById('root') as HTMLElement);
  root.render(
    //<React.StrictMode>
    <LoginPage />,
    //</React.StrictMode>
  );
});
