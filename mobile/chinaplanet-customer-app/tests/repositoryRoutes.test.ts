import { describe, expect, it, vi } from 'vitest';

import { MobileApiClient } from '@/src/api/client';
import { MobileRepository } from '@/src/repositories/mobileRepository';

describe('frozen repository routes', () => {
  it('uses only frozen customer routes and never sends customer selectors', async () => {
    const request = vi.fn(async (path: string, options?: unknown) => path.includes('/balances') ? { data: [], request_id: 'r' } : { data: [], meta: { limit: 25, has_more: false }, request_id: 'r' });
    const repository = new MobileRepository({ request } as unknown as MobileApiClient);
    await repository.projects(); await repository.shipments(); await repository.orders(); await repository.balances(); await repository.transactions(); await repository.invoices(); await repository.documents(); await repository.commercialDocuments(); await repository.reports();
    const serialized = JSON.stringify(request.mock.calls);
    expect(serialized).not.toMatch(/socid|fk_soc|entity|customer_id/i);
    expect(request.mock.calls.map((call) => call[0])).toEqual(['/projects', '/shipments', '/orders', '/account/balances', '/account/transactions', '/service-invoices', '/documents', '/commercial-documents', '/reports']);
  });

  it('encodes opaque ids before using detail routes', async () => {
    const request = vi.fn(async (_path: string) => ({ data: {}, request_id: 'r' })); const repository = new MobileRepository({ request } as unknown as MobileApiClient);
    await repository.project('opaque/id'); await repository.shipment('opaque?id'); await repository.report('opaque id');
    expect(request.mock.calls.map((call) => call[0])).toEqual(['/projects/opaque%2Fid', '/shipments/opaque%3Fid', '/reports/opaque%20id']);
  });

  it('forwards opaque cursors without adding customer authority fields', async () => {
    const request = vi.fn(async () => ({ data: [], meta: { limit: 25, has_more: false, next_cursor: null }, request_id: 'r' }));
    const repository = new MobileRepository({ request } as unknown as MobileApiClient);
    await repository.projects({ limit: 25, cursor: 'opaque/cursor' });
    expect(request).toHaveBeenCalledWith('/projects', { query: { limit: 25, cursor: 'opaque/cursor', updated_since: undefined } });
    expect(JSON.stringify(request.mock.calls)).not.toMatch(/socid|fk_soc|entity|customer_id/i);
  });
});
