import { afterEach, describe, expect, it } from 'vitest';

import getConfig from '../app.config';

afterEach(() => {
  delete process.env.CHINAPLANET_APP_VARIANT;
});

describe('mobile build variants', () => {
  it('keeps the official identity for production', () => {
    process.env.CHINAPLANET_APP_VARIANT = 'production';
    const config = getConfig();

    expect(config.name).toBe('chinaplanetCS');
    expect(config.android?.package).toBe('com.chinaplanet.customer');
    expect(config.ios?.bundleIdentifier).toBe('com.chinaplanet.customer');
    expect(config.scheme).toBe('chinaplanet');
  });

  it('creates a separately installable internal app', () => {
    process.env.CHINAPLANET_APP_VARIANT = 'internal';
    const config = getConfig();

    expect(config.name).toBe('chinaplanetCS Test');
    expect(config.android?.package).toBe('com.chinaplanet.customer.internal');
    expect(config.ios?.bundleIdentifier).toBe('com.chinaplanet.customer.internal');
    expect(config.scheme).toBe('chinaplanet-internal');
  });
});
