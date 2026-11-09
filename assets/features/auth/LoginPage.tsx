import { AuthShell } from './AuthShell';
import { LoginForm } from './LoginForm';

export const LoginPage = () => (
  <AuthShell title="Ingresa a tu cuenta" subtitle="Usa tu usuario y contraseña del ERP.">
    <LoginForm />
  </AuthShell>
);
