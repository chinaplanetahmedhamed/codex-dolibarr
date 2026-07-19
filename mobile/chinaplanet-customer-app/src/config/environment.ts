const LOCAL_API_BASE = 'http://127.0.0.1:8088/api/index.php/chinaplanetcustomercenter/mobile/v1';
type AppEnvironment = 'local' | 'backup-erp' | 'production';

const appEnvironment = (process.env.EXPO_PUBLIC_APP_ENV || 'local') as AppEnvironment;
if (!['local', 'backup-erp', 'production'].includes(appEnvironment)) {
  throw new Error('EXPO_PUBLIC_APP_ENV must be local, backup-erp, or production.');
}
if (appEnvironment !== 'local' && !process.env.EXPO_PUBLIC_API_BASE_URL) {
  throw new Error('Non-local builds require an explicitly approved API base URL.');
}
if (appEnvironment === 'production' && process.env.EXPO_PUBLIC_PRODUCTION_APPROVED !== 'true') {
  throw new Error('Production builds require exact deployment approval.');
}

export const environment = {
  name: appEnvironment,
  apiBaseUrl: (process.env.EXPO_PUBLIC_API_BASE_URL || LOCAL_API_BASE).replace(/\/$/, ''),
  requestTimeoutMs: 15_000,
  isFixtureMode: process.env.EXPO_PUBLIC_M017_FIXTURE_MODE === 'test',
} as const;

if (environment.isFixtureMode && process.env.NODE_ENV !== 'test') {
  throw new Error('Fixture mode is permitted in automated tests only.');
}
