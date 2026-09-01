import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { Platform } from 'react-native';

import { MobileApiClient, mobileApiClient } from '@/src/api/client';
import { environment } from '@/src/config/environment';
import { customerCache } from '@/src/storage/customerCache';

export type DownloadTarget =
  | { kind: 'document'; id: string }
  | { kind: 'report-document'; reportId: string; documentId: string }
  | { kind: 'qc-evidence'; reportId: string; evidenceId: string }
  | { kind: 'qc-pdf'; reportId: string; publicationId: string };

type DownloadAuthClient = {
  getAccessToken: () => Promise<string>;
  request: (path: string) => Promise<unknown>;
};

export function safeDownloadName(name: string) {
  const cleaned = name.replace(/[\\/:*?"<>|\r\n]/g, '_').replace(/^\.+/, '').slice(0, 180);
  return cleaned || 'document';
}

export function targetPath(target: DownloadTarget) {
  if (target.kind === 'document') return `/documents/${encodeURIComponent(target.id)}/content`;
  if (target.kind === 'report-document') return `/reports/${encodeURIComponent(target.reportId)}/documents/${encodeURIComponent(target.documentId)}/content`;
  if (target.kind === 'qc-evidence') return `/reports/${encodeURIComponent(target.reportId)}/documents/${encodeURIComponent(target.evidenceId)}/content`;
  return `/reports/${encodeURIComponent(target.reportId)}/documents/${encodeURIComponent(target.publicationId)}/content`;
}

export async function downloadAndShare(
  target: DownloadTarget,
  filename: string,
  client: MobileApiClient = mobileApiClient,
) {
  if (Platform.OS === 'web') return downloadForWeb(target, filename, client);
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

export async function fetchAuthenticatedDownload(
  target: DownloadTarget,
  client: DownloadAuthClient = mobileApiClient,
  fetcher: typeof fetch = fetch,
) {
  const request = (accessToken: string) => fetcher(
    `${environment.apiBaseUrl}${targetPath(target)}`,
    {
      method: 'GET',
      headers: { Authorization: `Bearer ${accessToken}`, Accept: '*/*' },
      cache: 'no-store',
      credentials: 'omit',
    },
  );
  let response = await request(await client.getAccessToken());
  if (response.status === 401) {
    await client.request('/me');
    response = await request(await client.getAccessToken());
  }
  return response;
}

export async function fetchAuthenticatedRange(
  target: DownloadTarget,
  start: number,
  end: number,
  client: DownloadAuthClient = mobileApiClient,
  fetcher: typeof fetch = fetch,
  signal?: AbortSignal,
) {
  const request = (accessToken: string) => fetcher(`${environment.apiBaseUrl}${targetPath(target)}`, {
    method: 'GET',
    headers: { Authorization: `Bearer ${accessToken}`, Accept: 'video/mp4', Range: `bytes=${start}-${end}` },
    cache: 'no-store',
    credentials: 'omit',
    signal,
  });
  let response = await request(await client.getAccessToken());
  if (response.status === 401) {
    await client.request('/me');
    response = await request(await client.getAccessToken());
  }
  return response;
}

async function downloadForWeb(target: DownloadTarget, filename: string, client: MobileApiClient) {
  const response = await fetchAuthenticatedDownload(target, client);
  if (response.status !== 200 && response.status !== 206) throw new Error(`download_failed_${response.status}`);
  const blob = await response.blob();
  const objectUrl = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = safeDownloadName(filename);
  anchor.rel = 'noopener';
  anchor.style.display = 'none';
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
  return objectUrl;
}

function performDownload(target: DownloadTarget, destination: string, accessToken: string) {
  return FileSystem.downloadAsync(
    `${environment.apiBaseUrl}${targetPath(target)}`,
    destination,
    { headers: { Authorization: `Bearer ${accessToken}`, Accept: '*/*' } },
  );
}
