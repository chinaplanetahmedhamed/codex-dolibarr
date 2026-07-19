import { Ionicons } from '@expo/vector-icons';
import * as Updates from 'expo-updates';
import { PropsWithChildren, useEffect, useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';

import { useI18n } from '@/src/i18n/I18nProvider';
import { palette, radii, shadows } from '@/src/theme';

type UpdateState = 'idle' | 'available' | 'downloading' | 'ready';

const isApprovedInternalRuntime = () => Updates.isEnabled && Updates.channel === 'internal' && Boolean(Updates.runtimeVersion);

export function InternalUpdateProvider({ children }: PropsWithChildren) {
  const { language } = useI18n();
  const [state, setState] = useState<UpdateState>('idle');
  const [dismissed, setDismissed] = useState(false);

  useEffect(() => {
    if (!isApprovedInternalRuntime()) return;
    let active = true;
    const timer = setTimeout(() => {
      void Updates.checkForUpdateAsync()
        .then((result) => { if (active && result.isAvailable) setState('available'); })
        .catch(() => undefined);
    }, 1500);
    return () => { active = false; clearTimeout(timer); };
  }, []);

  const download = async () => {
    if (!isApprovedInternalRuntime()) return;
    setState('downloading');
    try {
      const result = await Updates.fetchUpdateAsync();
      setState(result.isNew || result.isRollBackToEmbedded ? 'ready' : 'idle');
    } catch {
      // Keep the embedded or last-known working update. The app remains usable.
      setState('idle');
    }
  };

  const restart = async () => {
    if (!isApprovedInternalRuntime() || state !== 'ready') return;
    await Updates.reloadAsync();
  };

  const visible = !dismissed && state !== 'idle';
  const title = language === 'ar' ? 'تحديث جديد للتطبيق' : 'App update available';
  const description = state === 'ready'
    ? (language === 'ar' ? 'تم تنزيل التحديث. أعد تشغيل التطبيق لتطبيقه.' : 'The update is ready. Restart the app to apply it.')
    : state === 'downloading'
      ? (language === 'ar' ? 'جارٍ تنزيل التحديث الآمن…' : 'Downloading the secure update…')
      : (language === 'ar' ? 'يتوفر تحديث جديد لنسخة الاختبار الداخلية.' : 'A new internal-test update is available.');

  return <>
    {children}
    <Modal animationType="fade" onRequestClose={() => setDismissed(true)} transparent visible={visible}>
      <View style={styles.backdrop}>
        <View accessibilityRole="alert" style={styles.card}>
          <View style={styles.icon}><Ionicons name="cloud-download-outline" size={28} color={palette.brandRed} /></View>
          <Text style={styles.title}>{title}</Text>
          <Text style={styles.description}>{description}</Text>
          <View style={styles.actions}>
            {state === 'available' ? <Pressable accessibilityRole="button" onPress={() => void download()} style={styles.primary}><Text style={styles.primaryText}>{language === 'ar' ? 'تنزيل التحديث' : 'Download update'}</Text></Pressable> : null}
            {state === 'ready' ? <Pressable accessibilityRole="button" onPress={() => void restart()} style={styles.primary}><Text style={styles.primaryText}>{language === 'ar' ? 'إعادة التشغيل الآن' : 'Restart now'}</Text></Pressable> : null}
            {state !== 'downloading' ? <Pressable accessibilityRole="button" onPress={() => setDismissed(true)} style={styles.secondary}><Text style={styles.secondaryText}>{language === 'ar' ? 'لاحقًا' : 'Later'}</Text></Pressable> : null}
          </View>
          <Text style={styles.meta}>internal · {Updates.runtimeVersion ?? 'embedded'}</Text>
        </View>
      </View>
    </Modal>
  </>;
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: 'rgba(8,34,57,0.58)', alignItems: 'center', justifyContent: 'center', padding: 24 },
  card: { width: '100%', maxWidth: 430, backgroundColor: palette.surface, borderRadius: radii.large, padding: 22, gap: 12, alignItems: 'center', ...shadows.card },
  icon: { width: 54, height: 54, borderRadius: 18, backgroundColor: palette.brandRedSoft, alignItems: 'center', justifyContent: 'center' },
  title: { color: palette.navy, fontSize: 20, fontWeight: '900', textAlign: 'center' },
  description: { color: palette.text, fontSize: 13, lineHeight: 21, fontWeight: '700', textAlign: 'center' },
  actions: { width: '100%', gap: 9, marginTop: 4 },
  primary: { minHeight: 48, borderRadius: 14, backgroundColor: palette.brandRed, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16 },
  primaryText: { color: palette.white, fontWeight: '900' },
  secondary: { minHeight: 46, borderRadius: 14, borderWidth: 1, borderColor: palette.border, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16 },
  secondaryText: { color: palette.navy, fontWeight: '900' },
  meta: { color: palette.muted, fontSize: 9, textAlign: 'center' },
});
