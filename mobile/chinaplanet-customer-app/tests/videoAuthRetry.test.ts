import { describe, expect, it } from 'vitest';

import { createVideoAuthRetryGuard } from '@/src/files/videoAuthRetry';

describe('native QC video token renewal', () => {
  it('allows separate token expirations after each successful reinstall', () => {
    const guard = createVideoAuthRetryGuard();
    expect(guard.nextDelay()).toBe(500);
    guard.reset();
    expect(guard.nextDelay()).toBe(500);
    guard.reset();
    expect(guard.nextDelay()).toBe(500);
  });

  it('bounds repeated failures within one renewal incident', () => {
    const guard = createVideoAuthRetryGuard(3);
    expect([guard.nextDelay(), guard.nextDelay(), guard.nextDelay(), guard.nextDelay()]).toEqual([500, 1000, 2000, null]);
  });
});
