import type { ExpoConfig } from 'expo/config';

const EAS_PROJECT_ID = 'a69d974c-5792-466c-80b2-bdded33cf0fb';
const EXPO_OWNER = 'vision-trading';

export default (): ExpoConfig => {
  const isInternal = process.env.CHINAPLANET_APP_VARIANT === 'internal';

  return {
    name: isInternal ? 'chinaplanetCS Test' : 'chinaplanetCS',
    slug: 'chinaplanet-customer',
    owner: EXPO_OWNER,
    version: isInternal ? '1.0.2' : '1.0.0',
    orientation: 'portrait',
    icon: './assets/images/chinaplanet-logo.png',
    scheme: isInternal ? 'chinaplanet-internal' : 'chinaplanet',
    userInterfaceStyle: 'light',
    newArchEnabled: true,
    runtimeVersion: { policy: 'appVersion' },
    updates: {
      enabled: true,
      url: `https://u.expo.dev/${EAS_PROJECT_ID}`,
      checkAutomatically: 'NEVER',
      fallbackToCacheTimeout: 0,
    },
    ios: {
      supportsTablet: true,
      bundleIdentifier: isInternal ? 'com.chinaplanet.customer.internal' : 'com.chinaplanet.customer',
    },
    android: {
      adaptiveIcon: {
        backgroundColor: '#FFFFFF',
        foregroundImage: './assets/images/chinaplanet-logo.png',
      },
      edgeToEdgeEnabled: true,
      predictiveBackGestureEnabled: false,
      blockedPermissions: [
        'android.permission.READ_EXTERNAL_STORAGE',
        'android.permission.WRITE_EXTERNAL_STORAGE',
        'android.permission.SYSTEM_ALERT_WINDOW',
      ],
      package: isInternal ? 'com.chinaplanet.customer.internal' : 'com.chinaplanet.customer',
    },
    web: {
      output: 'single',
      favicon: './assets/images/chinaplanet-logo.png',
    },
    plugins: [
      'expo-router',
      'expo-localization',
      'expo-secure-store',
      [
        'expo-splash-screen',
        {
          image: './assets/images/chinaplanet-logo.png',
          imageWidth: 200,
          resizeMode: 'contain',
          backgroundColor: '#FFFFFF',
          dark: { backgroundColor: '#FFFFFF' },
        },
      ],
    ],
    experiments: {
      typedRoutes: true,
      reactCompiler: true,
    },
    extra: {
      eas: { projectId: EAS_PROJECT_ID },
      internalUpdateChannel: isInternal ? 'internal' : null,
    },
  };
};
