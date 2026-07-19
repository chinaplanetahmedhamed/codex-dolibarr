import * as SecureStore from 'expo-secure-store';
import * as Crypto from 'expo-crypto';

import { Session } from '@/src/api/types';

const SESSION_KEY = 'chinaplanet.m017.session.v1';
const DEVICE_KEY = 'chinaplanet.m017.device.v1';
const LANGUAGE_KEY = 'chinaplanet.m017.language.v1';

export type SecureStorageAdapter = {
  getItemAsync(key: string): Promise<string | null>;
  setItemAsync(key: string, value: string): Promise<void>;
  deleteItemAsync(key: string): Promise<void>;
};

const expoSecureStorage: SecureStorageAdapter = {
  getItemAsync: (key) => SecureStore.getItemAsync(key),
  setItemAsync: (key, value) =>
    SecureStore.setItemAsync(key, value, {
      keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
    }),
  deleteItemAsync: (key) => SecureStore.deleteItemAsync(key),
};

export class SecureSessionStore {
  constructor(private readonly storage: SecureStorageAdapter = expoSecureStorage) {}

  async readSession(): Promise<Session | null> {
    const serialized = await this.storage.getItemAsync(SESSION_KEY);
    if (!serialized) return null;
    try {
      const session = JSON.parse(serialized) as Partial<Session>;
      if (
        typeof session.access_token !== 'string' ||
        typeof session.refresh_token !== 'string' ||
        session.token_type !== 'Bearer' ||
        typeof session.refresh_expires_at !== 'string'
      ) {
        await this.clearSession();
        return null;
      }
      return session as Session;
    } catch {
      await this.clearSession();
      return null;
    }
  }

  writeSession(session: Session) {
    return this.storage.setItemAsync(SESSION_KEY, JSON.stringify(session));
  }

  clearSession() {
    return this.storage.deleteItemAsync(SESSION_KEY);
  }

  async getOrCreateDeviceId() {
    const existing = await this.storage.getItemAsync(DEVICE_KEY);
    if (existing) return existing;
    const created = Crypto.randomUUID();
    await this.storage.setItemAsync(DEVICE_KEY, created);
    return created;
  }

  readLanguage() {
    return this.storage.getItemAsync(LANGUAGE_KEY);
  }

  writeLanguage(language: 'ar' | 'en') {
    return this.storage.setItemAsync(LANGUAGE_KEY, language);
  }
}

export const secureSessionStore = new SecureSessionStore();
