import { useCallback, useEffect, useState } from 'react';

import { isSessionError, useAuth } from '@/src/auth/AuthProvider';

type ResourceState<T> = {
  data: T | null;
  loading: boolean;
  refreshing: boolean;
  error: unknown;
};

export function useResource<T>(loader: () => Promise<T>, dependencies: unknown[] = []) {
  const { expireSession } = useAuth();
  const [state, setState] = useState<ResourceState<T>>({ data: null, loading: true, refreshing: false, error: null });

  const load = useCallback(async (refreshing = false) => {
    setState((current) => ({ ...current, loading: !refreshing && current.data === null, refreshing, error: null }));
    try {
      const data = await loader();
      setState({ data, loading: false, refreshing: false, error: null });
    } catch (error) {
      if (isSessionError(error)) await expireSession();
      setState((current) => ({ ...current, loading: false, refreshing: false, error }));
    }
  // The caller controls dependency stability just like useEffect.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [expireSession, ...dependencies]);

  useEffect(() => { void load(); }, [load]);
  return { ...state, retry: () => load(false), refresh: () => load(true) };
}
