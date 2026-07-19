import { PropsWithChildren, ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { Money, Status } from '@/src/api/types';
import { useI18n } from '@/src/i18n/I18nProvider';
import { contentAlignment, leadingRowDirection, textAlignment } from '@/src/i18n/layout';
import { palette, radii, shadows } from '@/src/theme';

export function DataCard({ children }: PropsWithChildren) {
  return <View style={styles.card}>{children}</View>;
}

export function CardHeader({ title, status, trailing }: { title: string; status?: Status; trailing?: ReactNode }) {
  const { isRtl, label } = useI18n();
  const alignment = { textAlign: textAlignment(isRtl) } as const;
  return <View style={[styles.header, { flexDirection: leadingRowDirection(isRtl) }]}><View>{trailing}</View><View style={[styles.headerCopy, { alignItems: contentAlignment(isRtl) }]}><Text style={[styles.title, alignment]}>{title}</Text>{status && <Text style={[styles.status, alignment]}>{label(status.label)}</Text>}</View></View>;
}

export function DetailRow({ label, value, alert = false }: { label: string; value: string; alert?: boolean }) {
  const { isRtl } = useI18n();
  const align = textAlignment(isRtl);
  return <View style={[styles.row, { flexDirection: leadingRowDirection(isRtl) }]}><Text style={[styles.label, { textAlign: align }]}>{label}</Text><Text style={[styles.value, alert && styles.alert, { textAlign: align }]}>{value}</Text></View>;
}

export function MoneyValue({ money }: { money: Money }) {
  return <Text style={styles.money}>{money.amount} {money.currency}</Text>;
}

export function formatDate(value?: string | null, language: 'ar' | 'en' = 'ar') {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat(language === 'ar' ? 'ar' : 'en', { dateStyle: 'medium' }).format(date);
}

const styles = StyleSheet.create({
  card: { backgroundColor: palette.surface, borderRadius: radii.medium, borderWidth: 1, borderColor: palette.border, padding: 16, gap: 12, ...shadows.card },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 }, headerCopy: { flex: 1 },
  title: { color: palette.navy, fontSize: 16, lineHeight: 23, fontWeight: '900' }, status: { color: palette.blue, fontSize: 11, fontWeight: '800', marginTop: 4 },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 14, borderTopWidth: 1, borderTopColor: '#EAF0F3', paddingTop: 11 }, label: { flex: 1, color: palette.muted, fontSize: 11, fontWeight: '700' }, value: { color: palette.text, fontSize: 12, fontWeight: '900' }, alert: { color: palette.red }, money: { color: palette.blue, fontSize: 16, fontWeight: '900' },
});
