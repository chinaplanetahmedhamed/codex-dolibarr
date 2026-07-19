import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { afterEach, describe, expect, it } from 'vitest';

import getConfig from '../app.config';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

afterEach(() => {
  delete process.env.CHINAPLANET_APP_VARIANT;
  delete process.env.EAS_PROJECT_ID;
  delete process.env.EXPO_OWNER;
});

describe('internal EAS update controls', () => {
  it('pins the internal binary to the company project and app-version runtime', () => {
    process.env.CHINAPLANET_APP_VARIANT = 'internal';
    process.env.EAS_PROJECT_ID = '00000000-0000-4000-8000-000000000001';
    process.env.EXPO_OWNER = 'synthetic-owner';
    const config = getConfig();
    expect(config.version).toBe('1.0.1');
    expect(config.runtimeVersion).toEqual({ policy: 'appVersion' });
    expect(config.updates?.url).toBe('https://u.expo.dev/00000000-0000-4000-8000-000000000001');
    expect(config.updates?.checkAutomatically).toBe('NEVER');
    expect(config.android?.package).toBe('com.chinaplanet.customer.internal');
    expect(config.extra?.internalUpdateChannel).toBe('internal');
  });

  it('checks only the compiled internal channel and never overrides the update URL', () => {
    const provider = readFileSync(resolve(root, 'src/updates/InternalUpdateProvider.tsx'), 'utf8');
    expect(provider).toContain("Updates.channel === 'internal'");
    expect(provider).toContain('Updates.checkForUpdateAsync()');
    expect(provider).toContain('Updates.fetchUpdateAsync()');
    expect(provider).toContain('Updates.reloadAsync()');
    expect(provider).not.toContain('setUpdateURLAndRequestHeadersOverride');
    expect(provider).not.toContain('setUpdateRequestHeadersOverride');
  });

  it('keeps publication manual, branch-gated, internal-only, and native-change aware', () => {
    const workflow = readFileSync(resolve(root, '../../.github/workflows/mobile-internal-update.yml'), 'utf8');
    expect(workflow).toContain("github.ref == 'refs/heads/mobile-develop'");
    expect(workflow).toContain('PUBLISH_CHINAPLANET_INTERNAL_OTA');
    expect(workflow).toContain('environment: mobile-internal-ota');
    expect(workflow).toContain('eas update --channel internal');
    expect(workflow).toContain('BINARY_SOURCE_COMMIT');
    expect(workflow).not.toContain('eas submit');
    expect(workflow).not.toContain('--channel production');
  });
});
