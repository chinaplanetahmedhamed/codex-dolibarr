import { useCallback, useEffect, useState } from 'react';

import { ListEnvelope } from '@/src/api/types';
import { isSessionError, useAuth } from '@/src/auth/AuthProvider';

type PageLoader<T> = (cursor?: string) => Promise<ListEnvelope<T>>;

export function usePaginatedResource<T>(loader: PageLoader<T>, dependencies: unknown[] = []) {
  const { expireSession } = useAuth();
  const [items, setItems] = useState<T[]>([]);
  const [nextCursor, setNextCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<unknown>(null);

  const loadFirst = useCallback(async (refresh = false) => {
    if (refresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      const page = await loader();
      setItems(page.data);
      setHasMore(page.meta.has_more);
      setNextCursor(page.meta.next_cursor ?? null);
    } catch (caught) {
      if (isSessionError(caught)) await expireSession();
      setError(caught);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  // The caller controls loader dependency stability.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [expireSession, ...dependencies]);

  const loadMore = useCallback(async () => {
    if (!hasMore || !nextCursor || loadingMore) return;
    setLoadingMore(true);
    setError(null);
    try {
      const page = await loader(nextCursor);
      setItems((current) => [...current, ...page.data]);
      setHasMore(page.meta.has_more);
      setNextCursor(page.meta.next_cursor ?? null);
    } catch (caught) {
      if (isSessionError(caught)) await expireSession();
      setError(caught);
    } finally {
      setLoadingMore(false);
    }
  }, [expireSession, hasMore, loader, loadingMore, nextCursor]);

  useEffect(() => { void loadFirst(); }, [loadFirst]);
  return { items, loading, refreshing, loadingMore, hasMore, error, retry: () => loadFirst(false), refresh: () => loadFirst(true), loadMore };
}
