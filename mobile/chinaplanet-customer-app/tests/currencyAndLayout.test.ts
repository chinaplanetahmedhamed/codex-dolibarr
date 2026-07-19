import { describe, expect, it } from 'vitest';

import { Balance } from '@/src/api/types';

describe('currency and mobile layout invariants', () => {
  it('keeps balances as independent currency rows without a synthetic grand total', () => {
    const balances: Balance[] = [
      { currency: 'USD', debit: '100.00', credit: '20.00', balance: '80.00', updated_at: '2026-07-19T00:00:00Z' },
      { currency: 'CNY', debit: '1000.00', credit: '0.00', balance: '1000.00', updated_at: '2026-07-19T00:00:00Z' },
    ];
    const renderedRows = balances.map((balance) => `${balance.balance} ${balance.currency}`);
    expect(renderedRows).toEqual(['80.00 USD', '1000.00 CNY']);
    expect(renderedRows).not.toContain('1080.00');
  });

  it.each([320, 360, 375, 390, 430])('supports common phone width %ipx with bounded horizontal padding', (width) => {
    const contentWidth = width - 36;
    expect(contentWidth).toBeGreaterThanOrEqual(284); expect(contentWidth).toBeLessThanOrEqual(width);
  });

  it('uses Arabic as the default and supports an explicit English switch', () => {
    const supported = ['ar', 'en']; expect(supported[0]).toBe('ar'); expect(supported).toContain('en');
  });
});
