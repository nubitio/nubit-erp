import { Settings } from 'luxon';
import React from 'react';
import ReactDOM from 'react-dom/client';
import { EfactApp } from './EfactApp';
import './app.scss';

// Ensure all luxon DateTime operations default to Peru timezone (UTC-5, no DST)
Settings.defaultZone = 'America/Lima';

window.addEventListener('load', () => {
  if ('serviceWorker' in navigator) {
    void navigator.serviceWorker.register('/sw.js');
  }

  const root = ReactDOM.createRoot(document.getElementById('root') as HTMLElement);
  root.render(
    //<React.StrictMode>
    <EfactApp />,
    //</React.StrictMode>
  );
});
