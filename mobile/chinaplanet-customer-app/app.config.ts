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
    scheme: isInternal ? 'chinaplanet-internal' : base.scheme,
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
    },
  };
};
