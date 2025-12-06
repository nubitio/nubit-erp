import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { getUser, signIn as sendSignInRequest } from '../api/auth';
import type { AuthContextType, User } from '../types';
import { StorageUtils } from '../core/utils/StorageUtils';

function AuthProvider(props: React.PropsWithChildren<unknown>) {
  const [user, setUser] = useState<User>();
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async function () {
      const user = await getUser();
      if (user?.username) {
        setUser(user);
      }

      setLoading(false);
    })();
  }, []);

  const signIn = useCallback(async (email: string, password: string) => {
    const result = await sendSignInRequest(email, password);
    if (result.isOk) {
      setUser(result.data);
    }

    return result;
  }, []);

  const signOut = useCallback(() => {
    StorageUtils.removeItem('token');
    StorageUtils.removeItem('refreshToken');
    StorageUtils.removeItem('user');
    setUser(undefined);
  }, []);

  return <AuthContext.Provider value={{ user, signIn, signOut, loading }} {...props} />;
}

const AuthContext = createContext<AuthContextType>({ loading: false } as AuthContextType);
const useAuth = () => useContext(AuthContext);

export { AuthProvider, useAuth };
