import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';

import { MobileApiClient, mobileApiClient } from '@/src/api/client';
import { environment } from '@/src/config/environment';
import { customerCache } from '@/src/storage/customerCache';

export type DownloadTarget =
  | { kind: 'document'; id: string }
  | { kind: 'report-document'; reportId: string; documentId: string };

export function safeDownloadName(name: string) {
  const cleaned = name.replace(/[\\/:*?"<>|\r\n]/g, '_').replace(/^\.+/, '').slice(0, 180);
  return cleaned || 'document';
}

export function targetPath(target: DownloadTarget) {
  if (target.kind === 'document') return `/documents/${encodeURIComponent(target.id)}/content`;
  return `/reports/${encodeURIComponent(target.reportId)}/documents/${encodeURIComponent(target.documentId)}/content`;
}

export async function downloadAndShare(
  target: DownloadTarget,
  filename: string,
  client: MobileApiClient = mobileApiClient,
) {
  await customerCache.ensureDirectory();
  const destination = `${customerCache.directory}${Date.now()}-${safeDownloadName(filename)}`;
  let result = await performDownload(target, destination, await client.getAccessToken());
  if (result.status === 401) {
    await FileSystem.deleteAsync(destination, { idempotent: true });
    await client.request('/me');
    result = await performDownload(target, destination, await client.getAccessToken());
  }
  if (result.status !== 200 && result.status !== 206) {
    await FileSystem.deleteAsync(destination, { idempotent: true });
    throw new Error(`download_failed_${result.status}`);
  }
  if (!(await Sharing.isAvailableAsync())) return result.uri;
  await Sharing.shareAsync(result.uri, { dialogTitle: filename });
  return result.uri;
}

function performDownload(target: DownloadTarget, destination: string, accessToken: string) {
  return FileSystem.downloadAsync(
    `${environment.apiBaseUrl}${targetPath(target)}`,
    destination,
    { headers: { Authorization: `Bearer ${accessToken}`, Accept: '*/*' } },
  );
}
