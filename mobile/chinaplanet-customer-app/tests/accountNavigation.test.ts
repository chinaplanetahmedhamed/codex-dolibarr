import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

const root = resolve(__dirname, '..');

describe('account statement navigation', () => {
  it('owns transactions inside the Account tab stack', () => {
    const accountLayout = readFileSync(resolve(root, 'app/(tabs)/account/_layout.tsx'), 'utf8');
    const account = readFileSync(resolve(root, 'app/(tabs)/account/index.tsx'), 'utf8');
    const transactions = readFileSync(resolve(root, 'app/(tabs)/account/transactions.tsx'), 'utf8');

    expect(accountLayout).toContain('name="transactions"');
    expect(account).toContain('href="/account/transactions"');
    expect(transactions).toContain('mobileRepository.transactions');
  });

  it('keeps the former route as a compatibility redirect', () => {
    const legacyRoute = readFileSync(resolve(root, 'app/transactions.tsx'), 'utf8');
    expect(legacyRoute).toContain('<Redirect href="/account/transactions" />');
  });

  it('owns every More menu destination inside the More tab stack', () => {
    const moreLayout = readFileSync(resolve(root, 'app/(tabs)/more/_layout.tsx'), 'utf8');
    const more = readFileSync(resolve(root, 'app/(tabs)/more/index.tsx'), 'utf8');
    const destinations = ['orders', 'documents', 'service-invoices', 'commercial-documents', 'reports'];

    for (const destination of destinations) {
      expect(moreLayout).toContain(`name="${destination}"`);
      expect(more).toContain(`router.push('/more/${destination}')`);
      expect(readFileSync(resolve(root, `app/(tabs)/more/${destination}.tsx`), 'utf8').length).toBeGreaterThan(100);
    }
    expect(moreLayout).toContain('name="commercial-document/[id]"');
    expect(moreLayout).toContain('name="report/[id]"');
  });
});
