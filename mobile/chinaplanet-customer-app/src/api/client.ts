import NetInfo from '@react-native-community/netinfo';
import { Platform } from 'react-native';

import { MobileApiError, NetworkUnavailableError, RequestTimeoutError, isErrorEnvelope } from './errors';
import { ApiEnvelope, ListQuery, LoginRequest, Session } from './types';
import { environment } from '@/src/config/environment';
import { customerCache } from '@/src/storage/customerCache';
import { SecureSessionStore, secureSessionStore } from '@/src/storage/secureSession';

export type RequestOptions = {
  method?: 'GET' | 'POST';
  body?: unknown;
  auth?: boolean;
  retryAfterRefresh?: boolean;
  query?: Record<string, string | number | undefined>;
  timeoutMs?: number;
};

type Fetcher = typeof fetch;
type NetworkChecker = () => Promise<boolean>;
const defaultFetcher: Fetcher = (...args) => fetch(...args);

const defaultNetworkChecker: NetworkChecker = async () => {
  // NetInfo and navigator.onLine can both report an offline internet state
  // while the local QA API is reachable. Web is preview-only in M017, so let
  // fetch provide the authoritative reachable/offline result on that target.
  if (Platform.OS === 'web') return true;
  const state = await NetInfo.fetch();
  return state.isConnected !== false && state.isInternetReachable !== false;
};

export class MobileApiClient {
  private refreshInFlight: Promise<Session> | null = null;

  constructor(
    private readonly baseUrl = environment.apiBaseUrl,
    private readonly sessionStore: SecureSessionStore = secureSessionStore,
    private readonly fetcher: Fetcher = defaultFetcher,
    private readonly networkChecker: NetworkChecker = defaultNetworkChecker,
  ) {}

  async login(login: string, password: string) {
    const deviceId = await this.sessionStore.getOrCreateDeviceId();
    const request: LoginRequest = {
      login: login.trim(),
      password,
      device_id: deviceId,
      device_name: `${Platform.OS} ChinaPlanet Customer`,
    };
    const envelope = await this.request<ApiEnvelope<Session>>('/auth/login', {
      method: 'POST',
      body: request,
      auth: false,
    });
    // A successful login may belong to a different customer than a prior
    // device session. Remove every last-known response and downloaded file
    // before persisting the new account session.
    await customerCache.clear();
    await this.sessionStore.writeSession(envelope.data);
    return envelope.data;
  }

  async logout() {
    try {
      await this.request<void>('/auth/logout/revoke', { method: 'POST', retryAfterRefresh: false });
    } finally {
      await this.clearLocalCustomerState();
    }
  }

  async clearLocalCustomerState() {
    await Promise.all([this.sessionStore.clearSession(), customerCache.clear()]);
  }

  async request<T>(path: string, options: RequestOptions = {}): Promise<T> {
    if (!(await this.networkChecker())) throw new NetworkUnavailableError();
    const auth = options.auth !== false;
    let session = auth ? await this.sessionStore.readSession() : null;
    if (auth && !session) {
      throw new MobileApiError('session_expired', 401);
    }

    let response = await this.send(path, options, session?.access_token);
    if (response.status === 401 && auth && options.retryAfterRefresh !== false) {
      session = await this.refreshSession();
      response = await this.send(path, { ...options, retryAfterRefresh: false }, session.access_token);
    }

    if (response.status === 204) return undefined as T;
    if (response.ok) return (await response.json()) as T;
    throw await this.toApiError(response);
  }

  async getAccessToken() {
    const session = await this.sessionStore.readSession();
    if (!session) throw new MobileApiError('session_expired', 401);
    return session.access_token;
  }

  private refreshSession() {
    if (!this.refreshInFlight) {
      this.refreshInFlight = this.performRefresh().finally(() => {
        this.refreshInFlight = null;
      });
    }
    return this.refreshInFlight;
  }

  private async performRefresh() {
    const current = await this.sessionStore.readSession();
    if (!current) throw new MobileApiError('session_expired', 401);
    const deviceId = await this.sessionStore.getOrCreateDeviceId();
    try {
      const envelope = await this.request<ApiEnvelope<Session>>('/auth/refresh', {
        method: 'POST',
        body: { refresh_token: current.refresh_token, device_id: deviceId },
        auth: false,
        retryAfterRefresh: false,
      });
      await this.sessionStore.writeSession(envelope.data);
      return envelope.data;
    } catch (error) {
      await this.clearLocalCustomerState();
      throw error;
    }
  }

  private async send(path: string, options: RequestOptions, accessToken?: string) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), options.timeoutMs ?? environment.requestTimeoutMs);
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (options.body !== undefined) headers['Content-Type'] = 'application/json';
    if (accessToken) headers.Authorization = `Bearer ${accessToken}`;
    try {
      return await this.fetcher(this.buildUrl(path, options.query), {
        method: options.method ?? 'GET',
        headers,
        body: options.body === undefined ? undefined : JSON.stringify(options.body),
        signal: controller.signal,
      });
    } catch (error) {
      if (error instanceof Error && error.name === 'AbortError') throw new RequestTimeoutError();
      throw new NetworkUnavailableError();
    } finally {
      clearTimeout(timeout);
    }
  }

  private buildUrl(path: string, query?: RequestOptions['query']) {
    const url = `${this.baseUrl}${path.startsWith('/') ? path : `/${path}`}`;
    const params = new URLSearchParams();
    Object.entries(query ?? {}).forEach(([key, value]) => {
      if (value !== undefined) params.set(key, String(value));
    });
    const serialized = params.toString();
    return serialized ? `${url}?${serialized}` : url;
  }

  private async toApiError(response: Response) {
    let payload: unknown;
    try {
      payload = await response.json();
    } catch {
      payload = null;
    }
    if (isErrorEnvelope(payload)) {
      return new MobileApiError(
        payload.error.code,
        response.status,
        payload.request_id,
        payload.error.retry_after,
        payload.error.field_codes,
        payload.error.message,
      );
    }
    return new MobileApiError('server_error', response.status);
  }
}

export function listQuery(query: ListQuery = {}) {
  return {
    limit: query.limit ?? 25,
    cursor: query.cursor,
    updated_since: query.updated_since,
  };
}

export const mobileApiClient = new MobileApiClient();
