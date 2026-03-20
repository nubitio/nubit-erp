import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import type { AuthContextType, User } from '../../types';
import { StorageUtils } from '../../shared/lib/StorageUtils';
import { branchService } from '../../features/branch';
import { authService } from '../../features/auth';

function AuthProvider(props: React.PropsWithChildren<unknown>) {
  const [user, setUser] = useState<User>();
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async function () {
      if (branchService.hasSelectedBranch()) {
        const storedUser = StorageUtils.getItem('user') as User | null;
        if (storedUser?.username) {
          setUser(storedUser);
        }
      }

      setLoading(false);
    })();
  }, []);

  const signIn = useCallback(
    async (
      email: string,
      password: string,
    ): Promise<{ isOk: boolean; data?: User; message?: string }> => {
      return new Promise((resolve) => {
        authService.login(email, password).subscribe({
          next: (response) => {
            const userData = response.data.user as User | undefined;
            if (userData) {
              StorageUtils.setItem('user', userData);
              setUser(userData);
            }
            resolve({ isOk: true, data: userData });
          },
          error: (err) => {
            const message =
              err?.response?.data?.detail ||
              err?.response?.data?.message ||
              'Authentication failed';
            resolve({ isOk: false, message });
          },
        });
      });
    },
    [],
  );

  const signOut = useCallback(() => {
    StorageUtils.removeItem('user');
    branchService.clearSelectedBranchId();
    branchService.clearSelectedBranchName();
    setUser(undefined);
    // best-effort logout to clear cookie
    authService.logout();
  }, []);

  return <AuthContext.Provider value={{ user, signIn, signOut, loading }} {...props} />;
}

const AuthContext = createContext<AuthContextType>({ loading: false } as AuthContextType);
const useAuth = () => useContext(AuthContext);

export { AuthProvider, useAuth };
