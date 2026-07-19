import * as SecureStore from 'expo-secure-store';
import * as Crypto from 'expo-crypto';
import { Platform } from 'react-native';

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

// Expo SecureStore is a native Android/iOS facility and has no web backend.
// The web target is a local design/QA preview only, so keep its session in
// process memory: never LocalStorage, IndexedDB, cookies, or another persistent
// browser store. A reload intentionally requires a fresh sign-in.
const volatileWebValues = new Map<string, string>();
const volatileWebStorage: SecureStorageAdapter = {
  async getItemAsync(key) { return volatileWebValues.get(key) ?? null; },
  async setItemAsync(key, value) { volatileWebValues.set(key, value); },
  async deleteItemAsync(key) { volatileWebValues.delete(key); },
};

const defaultSecureStorage = Platform.OS === 'web' ? volatileWebStorage : expoSecureStorage;

function webDeviceUuid() {
  const bytes = new Uint8Array(16);
  globalThis.crypto.getRandomValues(bytes);
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export class SecureSessionStore {
  constructor(private readonly storage: SecureStorageAdapter = defaultSecureStorage) {}

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
    const created = Platform.OS === 'web'
      ? webDeviceUuid()
      : Crypto.randomUUID();
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
