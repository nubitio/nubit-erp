import React from 'react';
import { useAuth } from '@app/contexts/auth';
import { SmartCrudRolesProvider } from '@nubitio/crud';

export function SmartCrudAuthBridge({ children }: React.PropsWithChildren<unknown>) {
  const { user } = useAuth();

  return <SmartCrudRolesProvider roles={user?.roles ?? []}>{children}</SmartCrudRolesProvider>;
}
