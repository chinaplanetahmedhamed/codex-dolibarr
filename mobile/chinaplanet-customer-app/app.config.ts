import type { ExpoConfig } from 'expo/config';

import appJson from './app.json';

const base = appJson.expo as ExpoConfig;

export default (): ExpoConfig => {
  const projectId = process.env.EAS_PROJECT_ID?.trim();
  const owner = process.env.EXPO_OWNER?.trim();

  return {
    ...base,
    ...(owner ? { owner } : {}),
    extra: {
      ...(base.extra || {}),
      ...(projectId ? { eas: { projectId } } : {}),
    },
  };
};
