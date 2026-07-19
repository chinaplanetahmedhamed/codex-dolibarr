import { describe, expect, it } from 'vitest';

import { Session } from '@/src/api/types';
import { SecureSessionStore, SecureStorageAdapter } from '@/src/storage/secureSession';

class MemorySecureStorage implements SecureStorageAdapter {
  values = new Map<string, string>();
  async getItemAsync(key: string) { return this.values.get(key) ?? null; }
  async setItemAsync(key: string, value: string) { this.values.set(key, value); }
  async deleteItemAsync(key: string) { this.values.delete(key); }
}

const session: Session = {
  access_token: 'a'.repeat(43), refresh_token: 'r'.repeat(43), token_type: 'Bearer', access_expires_in: 900, refresh_expires_at: '2026-07-20T00:00:00+08:00',
};

describe('SecureSessionStore', () => {
  it('round-trips the session only through the secure adapter and clears it', async () => {
    const adapter = new MemorySecureStorage(); const store = new SecureSessionStore(adapter);
    await store.writeSession(session);
    expect(await store.readSession()).toEqual(session);
    expect([...adapter.values.values()][0]).toContain(session.refresh_token);
    await store.clearSession(); expect(await store.readSession()).toBeNull();
  });

  it('rejects malformed persisted sessions', async () => {
    const adapter = new MemorySecureStorage(); const store = new SecureSessionStore(adapter);
    adapter.values.set('chinaplanet.m017.session.v1', '{"access_token":"bad"}');
    expect(await store.readSession()).toBeNull();
    expect(adapter.values.size).toBe(0);
  });

  it('creates one stable UUID device id without embedding customer identity', async () => {
    const store = new SecureSessionStore(new MemorySecureStorage());
    const first = await store.getOrCreateDeviceId(); const second = await store.getOrCreateDeviceId();
    expect(first).toBe(second); expect(first).toMatch(/^[0-9a-f-]{36}$/); expect(first).not.toContain('socid');
  });
});
