import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it, vi } from 'vitest';

import { MobileApiClient } from '@/src/api/client';
import { MobileRepository } from '@/src/repositories/mobileRepository';

describe('linked customer documents presentation', () => {
  it('keeps the unified bilingual document labels and protected download flow in source', () => {
    const testsDirectory = dirname(fileURLToPath(import.meta.url));
    const detailSource = readFileSync(resolve(testsDirectory, '../app/shipment/[id].tsx'), 'utf8');
    const listSource = readFileSync(resolve(testsDirectory, '../app/(tabs)/more/documents.tsx'), 'utf8');
    const previewSource = readFileSync(resolve(testsDirectory, '../src/files/authenticatedPreview.ts'), 'utf8');
    const itemSource = readFileSync(resolve(testsDirectory, '../src/components/ShipmentGalleryItem.tsx'), 'utf8');
    expect(detailSource).toContain('معرض الشحنة');
    expect(detailSource).toContain('Shipment Gallery');
    expect(listSource).toContain('المستندات المرتبطة');
    expect(listSource).toContain('Linked Documents');
    expect(listSource).toContain('mobileRepository.documents');
    expect(listSource).toContain("document.source_type === 'commercial_document'");
    expect(listSource).toContain("'/more/commercial-document/[id]'");
    expect(previewSource).toContain('Authorization: `Bearer ${await client.getAccessToken()}`');
    expect(previewSource).not.toMatch(/[?&](token|access_token)=/i);
    expect(itemSource).toContain("return `${(safe / (1024 * 1024)).toFixed(1)} MB`");
  });

  it('retains the protected shipment-only repository method for compatible callers', async () => {
    const request = vi.fn(async () => ({ data: [], meta: { limit: 25, has_more: false }, request_id: 'r' }));
    const repository = new MobileRepository({ request } as unknown as MobileApiClient);
    await repository.shipmentDocuments({ limit: 25, cursor: 'opaque' });
    expect(request).toHaveBeenCalledWith('/documents', { query: { limit: 25, cursor: 'opaque', updated_since: undefined, scope: 'shipment' } });
    expect(JSON.stringify(request.mock.calls)).not.toMatch(/socid|fk_soc|entity|customer_id/i);
  });
});
