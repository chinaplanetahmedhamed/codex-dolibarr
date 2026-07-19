import * as FileSystem from 'expo-file-system/legacy';

const CUSTOMER_CACHE = `${FileSystem.cacheDirectory ?? ''}chinaplanet-m017/`;

export const customerCache = {
  directory: CUSTOMER_CACHE,
  async ensureDirectory() {
    if (!CUSTOMER_CACHE) throw new Error('cache_directory_unavailable');
    await FileSystem.makeDirectoryAsync(CUSTOMER_CACHE, { intermediates: true });
  },
  async clear() {
    if (!CUSTOMER_CACHE) return;
    await FileSystem.deleteAsync(CUSTOMER_CACHE, { idempotent: true });
  },
  async writeJson(key: string, value: unknown) {
    await this.ensureDirectory();
    await FileSystem.writeAsStringAsync(`${CUSTOMER_CACHE}${cacheFile(key)}`, JSON.stringify(value), {
      encoding: FileSystem.EncodingType.UTF8,
    });
  },
  async readJson<T>(key: string): Promise<T | null> {
    try {
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
