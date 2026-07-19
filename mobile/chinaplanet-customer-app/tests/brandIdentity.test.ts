import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

const root = resolve(__dirname, '..');
const logoPath = resolve(root, 'assets/images/chinaplanet-logo.png');

describe('official China Planet brand identity', () => {
  it('preserves the approved logo bytes', () => {
    const sha256 = createHash('sha256').update(readFileSync(logoPath)).digest('hex');
    expect(sha256).toBe('72cc0e517fe58938accb4a14f06cc776631109ffcf141dd82d0e00d4fdf32ba2');
  });

  it('uses the official logo for Expo identity surfaces', () => {
    const app = JSON.parse(readFileSync(resolve(root, 'app.json'), 'utf8')).expo;
    expect(app.icon).toBe('./assets/images/chinaplanet-logo.png');
    expect(app.android.adaptiveIcon.foregroundImage).toBe('./assets/images/chinaplanet-logo.png');
    expect(app.web.favicon).toBe('./assets/images/chinaplanet-logo.png');
    expect(app.plugins[3][1].image).toBe('./assets/images/chinaplanet-logo.png');
  });

  it('replaces temporary CP and V marks in visible application branding', () => {
    const login = readFileSync(resolve(root, 'app/index.tsx'), 'utf8');
    const components = readFileSync(resolve(root, 'src/components.tsx'), 'utf8');
    const home = readFileSync(resolve(root, 'app/(tabs)/home.tsx'), 'utf8');
    const theme = readFileSync(resolve(root, 'src/theme.ts'), 'utf8');
    expect(login).toContain("require('../assets/images/chinaplanet-logo.png')");
    expect(components).toContain("require('../assets/images/chinaplanet-logo.png')");
    expect(login).toContain('عملاء Vision Trading - كوكب الصين');
    expect(components).toContain('عملاء Vision Trading - كوكب الصين');
    expect(login).not.toContain('>CP<');
    expect(components).not.toContain('>V<');
    expect(home).toContain('brandLogo');
    expect(home).not.toContain('icon="planet"');
    expect(home).toContain("router.push('/projects')");
    expect(home).toContain("router.push('/shipments')");
    expect(home).toContain("router.push('/orders')");
    expect(theme).toContain("brandRed: '#D91436'");
    expect(theme).not.toContain('gold:');
  });
});
