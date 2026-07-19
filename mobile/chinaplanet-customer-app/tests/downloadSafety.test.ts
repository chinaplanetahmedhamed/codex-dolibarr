import { describe, expect, it } from 'vitest';

import { safeDownloadName, targetPath } from '@/src/files/authenticatedDownload';

describe('authenticated download paths', () => {
  it('constructs only frozen protected content routes', () => {
    expect(targetPath({ kind: 'document', id: 'abc/def' })).toBe('/documents/abc%2Fdef/content');
    expect(targetPath({ kind: 'report-document', reportId: 'report id', documentId: 'doc/id' })).toBe('/reports/report%20id/documents/doc%2Fid/content');
  });

  it('removes traversal and header characters from local filenames', () => {
    expect(safeDownloadName('../../secret\r\n.pdf')).toBe('_.._secret__.pdf');
    expect(safeDownloadName('')).toBe('document');
  });
});
