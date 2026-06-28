import { Navigate } from 'react-router-dom';

/** @deprecated Use /config/company or other settings hub tabs. */
export const ConfigPage = () => <Navigate to="/config/company" replace />;