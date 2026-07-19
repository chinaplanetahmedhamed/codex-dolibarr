import { ActivityIndicator, Pressable, StyleSheet, Text } from 'react-native';

import { useI18n } from '@/src/i18n/I18nProvider';
import { palette } from '@/src/theme';

export function PaginationFooter({ hasMore, loading, onPress }: { hasMore: boolean; loading: boolean; onPress: () => void }) {
  const { language } = useI18n();
  if (!hasMore) return null;
  return (
    <Pressable accessibilityRole="button" disabled={loading} onPress={onPress} style={styles.button}>
      {loading ? <ActivityIndicator color={palette.white} /> : <Text style={styles.text}>{language === 'ar' ? 'تحميل المزيد' : 'Load more'}</Text>}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  button: { minHeight: 48, borderRadius: 14, backgroundColor: palette.blue, alignItems: 'center', justifyContent: 'center', marginTop: 4 },
  text: { color: palette.white, fontWeight: '900' },
});
