import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { flushSync } from 'react-dom';
import type { AuthContextType, User } from '@app/types';
import { StorageUtils } from '../../shared/lib/StorageUtils';
import { branchService } from '../../features/branch';
import { authService } from '../../features/auth';
import { subscribeToAuthSessionSync } from '@shared/lib/authSessionSync';

function hasCommittedBranchContext(): boolean {
  return branchService.hasSelectedBranch() && branchService.hasSelectedBranchName();
}

function getStoredAuthenticatedUser(): User | undefined {
  if (!hasCommittedBranchContext()) {
    return undefined;
  }

  const storedUser = StorageUtils.getItem('user') as User | null;

  if (!storedUser?.username) {
    return undefined;
  }

  return storedUser;
}

export function hasCommittedAuthSession(): boolean {
  return getStoredAuthenticatedUser() !== undefined;
}

function AuthProvider(props: React.PropsWithChildren<unknown>) {
  const [user, setUser] = useState<User>();
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async function () {
      setUser(getStoredAuthenticatedUser());
      setLoading(false);
    })();
  }, []);

  useEffect(() => {
    const subscription = subscribeToAuthSessionSync(() => {
      flushSync(() => {
        setUser(getStoredAuthenticatedUser());
      });
    });

    return () => {
      subscription.unsubscribe();
    };
  }, []);

  const signIn = useCallback(
    async (
      email: string,
      password: string,
    ): Promise<{ isOk: boolean; data?: User; message?: string }> => {
      try {
        const response = await authService.login(email, password);
        const userData = response.data.user as User | undefined;
        if (userData) {
          StorageUtils.setItem('user', userData);
          setUser(userData);
        }
        return { isOk: true, data: userData };
      } catch (err: unknown) {
        const anyErr = err as { data?: { detail?: string; message?: string } };
        const message = anyErr?.data?.detail || anyErr?.data?.message || 'Authentication failed';
        return { isOk: false, message };
      }
    },
    [],
  );

  const signOut = useCallback(() => {
    StorageUtils.removeItem('user');
    branchService.clearSelectedBranchId();
    branchService.clearSelectedBranchName();
    setUser(undefined);
    // best-effort logout to clear cookie
    void authService.logout().catch(() => undefined);
  }, []);

  return <AuthContext.Provider value={{ user, signIn, signOut, loading }} {...props} />;
}

function throwNoProvider(): never {
  throw new Error('useAuth must be used within an AuthProvider');
}

const AuthContext = createContext<AuthContextType>({
  user: undefined,
  loading: false,
  signIn: throwNoProvider,
  signOut: throwNoProvider,
});
const useAuth = () => useContext(AuthContext);

export { AuthProvider, useAuth };
