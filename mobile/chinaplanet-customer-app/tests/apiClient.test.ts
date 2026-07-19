import * as FileSystem from 'expo-file-system/legacy';
import { describe, expect, it, vi } from 'vitest';

import { MobileApiClient } from '@/src/api/client';
import { NetworkUnavailableError } from '@/src/api/errors';
import { Session } from '@/src/api/types';
import { SecureSessionStore, SecureStorageAdapter } from '@/src/storage/secureSession';

class MemorySecureStorage implements SecureStorageAdapter {
  values = new Map<string, string>();
  async getItemAsync(key: string) { return this.values.get(key) ?? null; }
  async setItemAsync(key: string, value: string) { this.values.set(key, value); }
  async deleteItemAsync(key: string) { this.values.delete(key); }
}
const initial: Session = { access_token: 'a'.repeat(43), refresh_token: 'r'.repeat(43), token_type: 'Bearer', access_expires_in: 900, refresh_expires_at: '2026-07-20T00:00:00+08:00' };
const rotated: Session = { ...initial, access_token: 'b'.repeat(43), refresh_token: 's'.repeat(43) };
const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'content-type': 'application/json' } });

describe('MobileApiClient', () => {
  it('calls the default browser fetch without an illegal receiver binding', async () => {
    const browserFetch = vi.fn(async () => json({ data: initial, request_id: 'request_default_fetch' }));
    vi.stubGlobal('fetch', browserFetch);
    try {
      const client = new MobileApiClient('http://local/mobile/v1', new SecureSessionStore(new MemorySecureStorage()), undefined, async () => true);
      await client.login('customer', 'password');
      expect(browserFetch).toHaveBeenCalledTimes(1);
    } finally {
      vi.unstubAllGlobals();
    }
  });

  it('login sends only the frozen credential/device fields and saves returned tokens', async () => {
    const store = new SecureSessionStore(new MemorySecureStorage());
    const fetcher = vi.fn(async (_url: string | URL | Request, init?: RequestInit) => json({ data: initial, request_id: 'request_1234567890' }));
    const client = new MobileApiClient('http://local/mobile/v1', store, fetcher as typeof fetch, async () => true);
    await client.login('customer', 'password');
    const request = JSON.parse(String(fetcher.mock.calls[0][1]?.body));
    expect(Object.keys(request).sort()).toEqual(['device_id', 'device_name', 'login', 'password']);
    expect(request).not.toHaveProperty('socid'); expect(request).not.toHaveProperty('entity');
    expect(FileSystem.deleteAsync).toHaveBeenCalledWith('/tmp/chinaplanet-tests/chinaplanet-m017/', { idempotent: true });
    expect(await store.readSession()).toEqual(initial);
  });

  it('serially refreshes a rejected access token and retries with the rotated token', async () => {
    const store = new SecureSessionStore(new MemorySecureStorage()); await store.writeSession(initial);
    let count = 0;
    const fetcher = vi.fn(async (_url: string | URL | Request, init?: RequestInit) => {
      count += 1;
      if (count === 1) return json({ error: { code: 'session_expired', message: { ar: 'انتهت', en: 'Expired' } }, request_id: 'request_1234567890' }, 401);
      if (count === 2) return json({ data: rotated, request_id: 'request_2234567890' });
      expect((init?.headers as Record<string, string>).Authorization).toBe(`Bearer ${rotated.access_token}`);
      return json({ data: { ok: true }, request_id: 'request_3234567890' });
    });
    const client = new MobileApiClient('http://local/mobile/v1', store, fetcher as typeof fetch, async () => true);
    const response = await client.request<{ data: { ok: boolean } }>('/me');
    expect(response.data.ok).toBe(true); expect(await store.readSession()).toEqual(rotated); expect(fetcher).toHaveBeenCalledTimes(3);
  });

  it('maps stable server errors without exposing server internals', async () => {
    const fetcher = vi.fn(async () => json({ error: { code: 'rate_limited', message: { ar: 'انتظر', en: 'Wait' }, retry_after: 30 }, request_id: 'request_1234567890' }, 429));
    const client = new MobileApiClient('http://local/mobile/v1', new SecureSessionStore(new MemorySecureStorage()), fetcher as typeof fetch, async () => true);
    await expect(client.login('x', 'y')).rejects.toMatchObject({ code: 'rate_limited', status: 429, retryAfter: 30 });
  });

  it('stops before fetch when the device is offline', async () => {
    const fetcher = vi.fn(); const client = new MobileApiClient('http://local/mobile/v1', new SecureSessionStore(new MemorySecureStorage()), fetcher as typeof fetch, async () => false);
    await expect(client.login('x', 'y')).rejects.toBeInstanceOf(NetworkUnavailableError); expect(fetcher).not.toHaveBeenCalled();
  });
});
