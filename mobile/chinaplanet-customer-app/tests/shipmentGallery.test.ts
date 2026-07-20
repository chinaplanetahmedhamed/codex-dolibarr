import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it, vi } from 'vitest';

import { MobileApiClient } from '@/src/api/client';
import { MobileRepository } from '@/src/repositories/mobileRepository';

describe('shipment gallery customer presentation', () => {
  it('keeps the approved bilingual gallery labels and protected download flow in source', () => {
    const testsDirectory = dirname(fileURLToPath(import.meta.url));
    const detailSource = readFileSync(resolve(testsDirectory, '../app/shipment/[id].tsx'), 'utf8');
    const listSource = readFileSync(resolve(testsDirectory, '../app/(tabs)/more/documents.tsx'), 'utf8');
    const previewSource = readFileSync(resolve(testsDirectory, '../src/files/authenticatedPreview.ts'), 'utf8');
    const itemSource = readFileSync(resolve(testsDirectory, '../src/components/ShipmentGalleryItem.tsx'), 'utf8');
    expect(detailSource).toContain('معرض الشحنة');
    expect(detailSource).toContain('Shipment Gallery');
    expect(listSource).toContain('معارض الشحنات');
    expect(listSource).toContain('Shipment Galleries');
    expect(previewSource).toContain('Authorization: `Bearer ${await client.getAccessToken()}`');
    expect(previewSource).not.toMatch(/[?&](token|access_token)=/i);
    expect(itemSource).toContain("return `${(safe / (1024 * 1024)).toFixed(1)} MB`");
  });

  it('requests the protected documents route with shipment scope and no customer authority fields', async () => {
    const request = vi.fn(async () => ({ data: [], meta: { limit: 25, has_more: false }, request_id: 'r' }));
    const repository = new MobileRepository({ request } as unknown as MobileApiClient);
    await repository.shipmentDocuments({ limit: 25, cursor: 'opaque' });
    expect(request).toHaveBeenCalledWith('/documents', { query: { limit: 25, cursor: 'opaque', updated_since: undefined, scope: 'shipment' } });
    expect(JSON.stringify(request.mock.calls)).not.toMatch(/socid|fk_soc|entity|customer_id/i);
  });
});
