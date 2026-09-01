import * as FileSystem from 'expo-file-system/legacy';
import { Platform } from 'react-native';

import { MobileApiClient, mobileApiClient } from '@/src/api/client';
import { environment } from '@/src/config/environment';
import { DownloadTarget, fetchAuthenticatedDownload, safeDownloadName, targetPath } from '@/src/files/authenticatedDownload';
import { customerCache } from '@/src/storage/customerCache';

export type AuthenticatedPreview = { uri: string; revoke: () => void };

export async function loadAuthenticatedPreview(
  target: DownloadTarget,
  cacheKey: string,
  filename: string,
  client: MobileApiClient = mobileApiClient,
): Promise<AuthenticatedPreview> {
  if (Platform.OS === 'web') {
    const response = await fetchAuthenticatedDownload(target, client);
    if (!response.ok) throw new Error(`preview_failed_${response.status}`);
    const uri = URL.createObjectURL(await response.blob());
    return { uri, revoke: () => URL.revokeObjectURL(uri) };
  }

  await customerCache.ensureDirectory();
  const key = cacheKey.replace(/[^A-Za-z0-9_-]/g, '_').slice(0, 120);
  const destination = `${customerCache.directory}preview-${key}-${safeDownloadName(filename)}`;
  const existing = await FileSystem.getInfoAsync(destination);
  if (existing.exists && !existing.isDirectory && (existing.size ?? 0) > 0) return { uri: existing.uri, revoke: () => undefined };

  let result = await FileSystem.downloadAsync(
    `${environment.apiBaseUrl}${targetPath(target)}`,
    destination,
    { headers: { Authorization: `Bearer ${await client.getAccessToken()}`, Accept: '*/*' } },
  );
  if (result.status === 401) {
    await FileSystem.deleteAsync(destination, { idempotent: true });
    await client.request('/me');
    result = await FileSystem.downloadAsync(
      `${environment.apiBaseUrl}${targetPath(target)}`,
      destination,
      { headers: { Authorization: `Bearer ${await client.getAccessToken()}`, Accept: '*/*' } },
    );
  }
  if (result.status !== 200 && result.status !== 206) {
    await FileSystem.deleteAsync(destination, { idempotent: true });
    throw new Error(`preview_failed_${result.status}`);
  }
  return { uri: result.uri, revoke: () => undefined };
}
