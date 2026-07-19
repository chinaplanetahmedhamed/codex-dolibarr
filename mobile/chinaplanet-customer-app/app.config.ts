import type { ExpoConfig } from 'expo/config';

import appJson from './app.json';

const base = appJson.expo as ExpoConfig;

export default (): ExpoConfig => {
  const projectId = process.env.EAS_PROJECT_ID?.trim();
  const owner = process.env.EXPO_OWNER?.trim();
  const isInternal = process.env.CHINAPLANET_APP_VARIANT === 'internal';

  return {
    ...base,
    name: isInternal ? 'chinaplanetCS Test' : base.name,
    version: isInternal ? '1.0.1' : base.version,
    scheme: isInternal ? 'chinaplanet-internal' : base.scheme,
    runtimeVersion: { policy: 'appVersion' },
    updates: projectId ? {
      url: `https://u.expo.dev/${projectId}`,
      checkAutomatically: 'NEVER',
      fallbackToCacheTimeout: 0,
    } : { enabled: false },
    android: {
      ...base.android,
      package: isInternal ? 'com.chinaplanet.customer.internal' : 'com.chinaplanet.customer',
    },
    ios: {
      ...base.ios,
      bundleIdentifier: isInternal ? 'com.chinaplanet.customer.internal' : 'com.chinaplanet.customer',
    },
    ...(owner ? { owner } : {}),
    extra: {
      ...(base.extra || {}),
      ...(projectId ? { eas: { projectId } } : {}),
      internalUpdateChannel: isInternal ? 'internal' : null,
    },
  };
};
