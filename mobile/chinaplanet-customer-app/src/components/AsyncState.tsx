import { Ionicons } from '@expo/vector-icons';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { MobileApiError, NetworkUnavailableError, RequestTimeoutError } from '@/src/api/errors';
import { useI18n } from '@/src/i18n/I18nProvider';
import { palette, radii } from '@/src/theme';

export function LoadingState() {
  const { t } = useI18n();
  return <View style={styles.state}><ActivityIndicator color={palette.brandRedLight} /><Text style={styles.text}>{t('loading')}</Text></View>;
}

export function EmptyState() {
  const { t } = useI18n();
  return <View style={styles.state}><Ionicons name="file-tray-outline" size={34} color={palette.muted} /><Text style={styles.text}>{t('empty')}</Text></View>;
}

export function ErrorState({ error, retry }: { error: unknown; retry: () => void }) {
  const { language, t } = useI18n();
  let message = t('genericError');
  if (error instanceof NetworkUnavailableError) message = t('offline');
  if (error instanceof RequestTimeoutError) message = t('timeout');
  if (error instanceof MobileApiError) message = error.localized(language);
  return (
    <View style={styles.state} accessibilityRole="alert">
      <Ionicons name="alert-circle-outline" size={34} color={palette.red} />
      <Text style={styles.text}>{message}</Text>
      <Pressable accessibilityRole="button" onPress={retry} style={styles.retry}><Text style={styles.retryText}>{t('retry')}</Text></Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  state: { minHeight: 220, alignItems: 'center', justifyContent: 'center', gap: 14, padding: 24, backgroundColor: palette.surface, borderRadius: radii.medium },
  text: { color: palette.text, fontSize: 14, lineHeight: 22, fontWeight: '700', textAlign: 'center' },
  retry: { minHeight: 44, minWidth: 140, borderRadius: 12, backgroundColor: palette.navy, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 18 },
  retryText: { color: palette.white, fontWeight: '900' },
});
