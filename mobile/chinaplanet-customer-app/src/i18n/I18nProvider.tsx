import { PropsWithChildren, createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { I18nManager, View } from 'react-native';

import { Language } from '@/src/api/types';
import { secureSessionStore } from '@/src/storage/secureSession';

type Copy = { ar: string; en: string };
const copy = {
  retry: { ar: 'إعادة المحاولة', en: 'Try again' },
  loading: { ar: 'جارٍ تحميل بياناتك…', en: 'Loading your data…' },
  empty: { ar: 'لا توجد بيانات متاحة حاليًا.', en: 'No data is currently available.' },
  offline: { ar: 'أنت غير متصل. تحقق من الإنترنت ثم أعد المحاولة.', en: 'You are offline. Check your connection and try again.' },
  timeout: { ar: 'استغرق الاتصال وقتًا طويلًا.', en: 'The request took too long.' },
  genericError: { ar: 'تعذر تحميل البيانات الآن.', en: 'The data could not be loaded.' },
  signIn: { ar: 'تسجيل الدخول', en: 'Sign in' },
  username: { ar: 'اسم المستخدم أو البريد', en: 'Username or email' },
  password: { ar: 'كلمة المرور', en: 'Password' },
  logout: { ar: 'تسجيل الخروج', en: 'Logout' },
} satisfies Record<string, Copy>;

type CopyKey = keyof typeof copy;
type I18nValue = {
  language: Language;
  isRtl: boolean;
  t(key: CopyKey): string;
  label(value?: Copy | null): string;
  setLanguage(language: Language): Promise<void>;
};

const I18nContext = createContext<I18nValue | null>(null);

export function I18nProvider({ children }: PropsWithChildren) {
  const [language, setLanguageState] = useState<Language>('ar');

  useEffect(() => {
    let mounted = true;
    void secureSessionStore.readLanguage().then((saved) => {
      const next: Language = saved === 'en' ? 'en' : 'ar';
      if (mounted) setLanguageState(next);
    });
    return () => { mounted = false; };
  }, []);

  useEffect(() => {
    const rtl = language === 'ar';
    I18nManager.allowRTL(true);
    if (I18nManager.isRTL !== rtl) I18nManager.forceRTL(rtl);
  }, [language]);

  const setLanguage = useCallback(async (next: Language) => {
    await secureSessionStore.writeLanguage(next);
    setLanguageState(next);
  }, []);

  const value = useMemo<I18nValue>(() => ({
    language,
    isRtl: language === 'ar',
    t: (key) => copy[key][language],
    label: (valueToTranslate) => valueToTranslate?.[language] ?? '',
    setLanguage,
  }), [language, setLanguage]);

  return <I18nContext.Provider value={value}><View style={{ flex: 1, direction: language === 'ar' ? 'rtl' : 'ltr' }}>{children}</View></I18nContext.Provider>;
}

export function useI18n() {
  const value = useContext(I18nContext);
  if (!value) throw new Error('useI18n must be used inside I18nProvider');
  return value;
}
