import * as FileSystem from 'expo-file-system/legacy';
import { Platform } from 'react-native';

const CUSTOMER_CACHE = `${FileSystem.cacheDirectory ?? ''}chinaplanet-m017/`;
const volatileWebCache = new Map<string, string>();

export const customerCache = {
  directory: CUSTOMER_CACHE,
  async ensureDirectory() {
    if (Platform.OS === 'web') return;
    if (!CUSTOMER_CACHE) throw new Error('cache_directory_unavailable');
    await FileSystem.makeDirectoryAsync(CUSTOMER_CACHE, { intermediates: true });
  },
  async clear() {
    if (Platform.OS === 'web') { volatileWebCache.clear(); return; }
    if (!CUSTOMER_CACHE) return;
    await FileSystem.deleteAsync(CUSTOMER_CACHE, { idempotent: true });
  },
  async writeJson(key: string, value: unknown) {
    if (Platform.OS === 'web') { volatileWebCache.set(cacheFile(key), JSON.stringify(value)); return; }
    await this.ensureDirectory();
    await FileSystem.writeAsStringAsync(`${CUSTOMER_CACHE}${cacheFile(key)}`, JSON.stringify(value), {
      encoding: FileSystem.EncodingType.UTF8,
    });
  },
  async readJson<T>(key: string): Promise<T | null> {
    try {
      if (Platform.OS === 'web') {
        const serialized = volatileWebCache.get(cacheFile(key));
        return serialized ? JSON.parse(serialized) as T : null;
      }
      const serialized = await FileSystem.readAsStringAsync(`${CUSTOMER_CACHE}${cacheFile(key)}`, {
        encoding: FileSystem.EncodingType.UTF8,
      });
      return JSON.parse(serialized) as T;
    } catch {
      return null;
    }
  },
};

function cacheFile(key: string) {
  return `${key.replace(/[^A-Za-z0-9_-]/g, '_').slice(0, 160)}.json`;
}
