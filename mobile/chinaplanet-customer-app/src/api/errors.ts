import { ApiErrorCode, ErrorEnvelope, Language } from './types';

export class MobileApiError extends Error {
  constructor(
    public readonly code: ApiErrorCode,
    public readonly status: number,
    public readonly requestId?: string,
    public readonly retryAfter?: number,
    public readonly fieldCodes: string[] = [],
    public readonly labels?: { ar: string; en: string },
  ) {
    super(labels?.en ?? code);
    this.name = 'MobileApiError';
  }

  localized(language: Language) {
    return this.labels?.[language] ?? fallbackMessages[this.code][language];
  }
}

export class NetworkUnavailableError extends Error {
  constructor() {
    super('network_unavailable');
    this.name = 'NetworkUnavailableError';
  }
}

export class RequestTimeoutError extends Error {
  constructor() {
    super('request_timeout');
    this.name = 'RequestTimeoutError';
  }
}

export function isErrorEnvelope(value: unknown): value is ErrorEnvelope {
  if (!value || typeof value !== 'object') return false;
  const candidate = value as Partial<ErrorEnvelope>;
  return typeof candidate.request_id === 'string' && typeof candidate.error?.code === 'string';
}

const fallbackMessages: Record<ApiErrorCode, { ar: string; en: string }> = {
  bad_request: { ar: 'الطلب غير صالح.', en: 'The request is invalid.' },
  invalid_credentials: { ar: 'بيانات الدخول غير صحيحة.', en: 'The sign-in details are incorrect.' },
  session_expired: { ar: 'انتهت الجلسة. سجّل الدخول مجددًا.', en: 'Your session expired. Please sign in again.' },
  forbidden: { ar: 'غير مسموح بهذا الإجراء.', en: 'This action is not permitted.' },
  not_found: { ar: 'العنصر غير متاح.', en: 'The item is not available.' },
  conflict: { ar: 'تعارضت حالة الجلسة. سجّل الدخول مجددًا.', en: 'The session state conflicted. Please sign in again.' },
  validation: { ar: 'تحقق من البيانات المدخلة.', en: 'Please check the submitted information.' },
  rate_limited: { ar: 'محاولات كثيرة. حاول بعد قليل.', en: 'Too many attempts. Try again shortly.' },
  server_error: { ar: 'تعذر إكمال الطلب الآن.', en: 'The request could not be completed.' },
};
