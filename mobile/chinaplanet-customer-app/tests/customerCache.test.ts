import * as FileSystem from 'expo-file-system/legacy';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { customerCache } from '@/src/storage/customerCache';

describe('session-scoped customer cache', () => {
  beforeEach(() => vi.clearAllMocks());

  it('writes JSON only below the app cache directory with a sanitized key', async () => {
    await customerCache.writeJson('../balances/customer', { data: ['USD'] });
    expect(FileSystem.writeAsStringAsync).toHaveBeenCalledWith(
      '/tmp/chinaplanet-tests/chinaplanet-m017/___balances_customer.json',
      JSON.stringify({ data: ['USD'] }),
      { encoding: 'utf8' },
    );
  });

  it('reads a last-known snapshot and clears the whole session cache on logout', async () => {
    vi.mocked(FileSystem.readAsStringAsync).mockResolvedValueOnce(JSON.stringify({ data: ['CNY'] }));
    expect(await customerCache.readJson('balances')).toEqual({ data: ['CNY'] });
    await customerCache.clear();
    expect(FileSystem.deleteAsync).toHaveBeenCalledWith('/tmp/chinaplanet-tests/chinaplanet-m017/', { idempotent: true });
  });
});
