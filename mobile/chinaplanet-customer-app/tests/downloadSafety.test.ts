import { describe, expect, it } from 'vitest';

import { fetchAuthenticatedDownload, safeDownloadName, targetPath } from '@/src/files/authenticatedDownload';

describe('authenticated download paths', () => {
  it('constructs only frozen protected content routes', () => {
    expect(targetPath({ kind: 'document', id: 'abc/def' })).toBe('/documents/abc%2Fdef/content');
    expect(targetPath({ kind: 'report-document', reportId: 'report id', documentId: 'doc/id' })).toBe('/reports/report%20id/documents/doc%2Fid/content');
  });

  it('removes traversal and header characters from local filenames', () => {
    expect(safeDownloadName('../../secret\r\n.pdf')).toBe('_.._secret__.pdf');
    expect(safeDownloadName('')).toBe('document');
  });

  it('keeps bearer credentials in request headers and refreshes once after 401', async () => {
    const accessTokens = ['expired-token', 'fresh-token'];
    const client = {
      getAccessToken: async () => accessTokens.shift() ?? 'fresh-token',
      request: async () => ({ data: {} }),
    };
    const calls: Array<{ url: string; authorization: string }> = [];
    const fetcher = async (input: RequestInfo | URL, init?: RequestInit) => {
      const headers = init?.headers as Record<string, string>;
      calls.push({ url: String(input), authorization: headers.Authorization });
      return new Response('', { status: calls.length === 1 ? 401 : 200 });
    };

    const response = await fetchAuthenticatedDownload({ kind: 'document', id: 'opaque-id' }, client, fetcher);
    expect(response.status).toBe(200);
    expect(calls).toHaveLength(2);
    expect(calls[0].authorization).toBe('Bearer expired-token');
    expect(calls[1].authorization).toBe('Bearer fresh-token');
    expect(calls.every((call) => !call.url.includes('token'))).toBe(true);
  });
});
