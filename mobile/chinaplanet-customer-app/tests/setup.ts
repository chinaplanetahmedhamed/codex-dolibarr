import { vi } from 'vitest';

vi.mock('expo-secure-store', () => ({
  WHEN_UNLOCKED_THIS_DEVICE_ONLY: 'device-only',
  getItemAsync: vi.fn(),
  setItemAsync: vi.fn(),
  deleteItemAsync: vi.fn(),
}));
vi.mock('expo-crypto', () => ({ randomUUID: vi.fn(() => '11111111-1111-4111-8111-111111111111') }));

vi.mock('expo-file-system/legacy', () => ({
  cacheDirectory: '/tmp/chinaplanet-tests/',
  makeDirectoryAsync: vi.fn(),
  deleteAsync: vi.fn(),
  downloadAsync: vi.fn(),
  writeAsStringAsync: vi.fn(),
  readAsStringAsync: vi.fn(),
  EncodingType: { UTF8: 'utf8' },
}));

vi.mock('expo-sharing', () => ({ isAvailableAsync: vi.fn(), shareAsync: vi.fn() }));
vi.mock('@react-native-community/netinfo', () => ({ default: { fetch: vi.fn(async () => ({ isConnected: true, isInternetReachable: true })) }, useNetInfo: vi.fn(() => ({ isConnected: true, isInternetReachable: true })) }));
vi.mock('react-native', () => ({ Platform: { OS: 'test' } }));
