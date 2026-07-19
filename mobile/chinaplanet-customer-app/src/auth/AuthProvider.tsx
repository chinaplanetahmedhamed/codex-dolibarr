import { PropsWithChildren, createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

import { MobileApiError } from '@/src/api/errors';
import { Me } from '@/src/api/types';
import { mobileApiClient } from '@/src/api/client';
import { mobileRepository } from '@/src/repositories/mobileRepository';
import { secureSessionStore } from '@/src/storage/secureSession';

type AuthStatus = 'booting' | 'anonymous' | 'authenticated';

type AuthContextValue = {
  status: AuthStatus;
  user: Me | null;
  login(login: string, password: string): Promise<void>;
  logout(): Promise<void>;
  expireSession(): Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: PropsWithChildren) {
  const [status, setStatus] = useState<AuthStatus>('booting');
  const [user, setUser] = useState<Me | null>(null);

  const expireSession = useCallback(async () => {
    await mobileApiClient.clearLocalCustomerState();
    setUser(null);
    setStatus('anonymous');
  }, []);

  useEffect(() => {
    let mounted = true;
    void (async () => {
      const session = await secureSessionStore.readSession();
      if (!session) {
        if (mounted) setStatus('anonymous');
        return;
      }
      try {
        const me = await mobileRepository.me();
        if (mounted) {
          setUser(me);
          setStatus('authenticated');
        }
      } catch (error) {
        if (isSessionError(error)) await mobileApiClient.clearLocalCustomerState();
        if (mounted) {
          setUser(null);
          setStatus(isSessionError(error) ? 'anonymous' : 'authenticated');
        }
      }
    })();
    return () => { mounted = false; };
  }, []);

  const login = useCallback(async (loginValue: string, password: string) => {
    await mobileApiClient.login(loginValue, password);
    try {
      const me = await mobileRepository.me();
      setUser(me);
      setStatus('authenticated');
    } catch (error) {
      if (isSessionError(error)) {
        await mobileApiClient.clearLocalCustomerState();
        throw error;
      }
      setUser(null);
      setStatus('authenticated');
    }
  }, []);

  const logout = useCallback(async () => {
    try {
      await mobileApiClient.logout();
    } finally {
      setUser(null);
      setStatus('anonymous');
    }
  }, []);

  const value = useMemo(() => ({ status, user, login, logout, expireSession }), [status, user, login, logout, expireSession]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const value = useContext(AuthContext);
  if (!value) throw new Error('useAuth must be used inside AuthProvider');
  return value;
}

export function isSessionError(error: unknown) {
  return error instanceof MobileApiError && (error.code === 'session_expired' || error.code === 'conflict');
}
