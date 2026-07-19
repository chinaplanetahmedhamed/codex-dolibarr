import { Link } from 'expo-router';
import { Pressable, StyleSheet, Text } from 'react-native';

import { AppScreen, BrandHeader, Hero, SectionHeader } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { useResource } from '@/src/hooks/useResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';
import { palette } from '@/src/theme';

export default function AccountScreen() {
  const { language } = useI18n();
  const resource = useResource(() => mobileRepository.balances());
  if (resource.loading) return <AppScreen><BrandHeader titleAr="الحساب" titleEn="Account" /><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><BrandHeader titleAr="الحساب" titleEn="Account" /><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const balances = resource.data ?? [];
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="الحساب" titleEn="Account" /><Hero eyebrow="READ ONLY ACCOUNT" title={language === 'ar' ? 'أرصدتك' : 'Your balances'} subtitle={language === 'ar' ? 'كل عملة تظهر منفصلة دون جمع العملات.' : 'Each currency is shown separately and never combined.'} icon="wallet" /><SectionHeader ar="الأرصدة حسب العملة" en="Balances by currency" />{balances.length === 0 ? <EmptyState /> : balances.map((balance) => <DataCard key={balance.currency}><DetailRow label={language === 'ar' ? 'العملة' : 'Currency'} value={balance.currency} /><DetailRow label={language === 'ar' ? 'مدين' : 'Debit'} value={balance.debit} /><DetailRow label={language === 'ar' ? 'دائن' : 'Credit'} value={balance.credit} /><DetailRow label={language === 'ar' ? 'الرصيد' : 'Balance'} value={balance.balance} alert={Number(balance.balance) > 0} /><Text style={styles.updated}>{formatDate(balance.updated_at, language)}</Text></DataCard>)}<Link href="/transactions" asChild><Pressable style={styles.button}><Text style={styles.buttonText}>{language === 'ar' ? 'فتح كشف الحساب' : 'Open account statement'}</Text></Pressable></Link></AppScreen>;
}

const styles = StyleSheet.create({ updated: { color: palette.muted, fontSize: 9, textAlign: 'center' }, button: { minHeight: 50, borderRadius: 14, backgroundColor: palette.navy, alignItems: 'center', justifyContent: 'center' }, buttonText: { color: palette.white, fontWeight: '900' } });
