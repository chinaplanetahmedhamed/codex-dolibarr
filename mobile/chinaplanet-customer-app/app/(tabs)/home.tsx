import { Ionicons } from '@expo/vector-icons';
import { Link, router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { AppScreen, BrandHeader, Hero, MetricCard, ProgressBar, SectionHeader, StatusPill } from '@/src/components';
import { useAuth } from '@/src/auth/AuthProvider';
import { useResource } from '@/src/hooks/useResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';
import { palette } from '@/src/theme';

export default function HomeScreen() {
  const { user } = useAuth();
  const { language, isRtl } = useI18n();
  const resource = useResource(() => mobileRepository.dashboard());
  if (resource.loading) return <AppScreen><BrandHeader /><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><BrandHeader /><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const dashboard = resource.data;
  if (!dashboard) return <AppScreen><BrandHeader /><EmptyState /></AppScreen>;

  return (
    <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}>
      <BrandHeader />
      <Hero eyebrow="CHINAPLANET CUSTOMER" title={user?.company_name || user?.display_name || '—'} subtitle={`${language === 'ar' ? 'آخر تحديث' : 'Last update'}: ${formatDate(dashboard.updated_at, language)}`} brandLogo />
      <View style={styles.metrics}>
        <MetricCard icon="folder-open" value={String(dashboard.active_projects)} ar="مشاريع نشطة" en="Active projects" tone="navy" onPress={() => router.push('/projects')} />
        <MetricCard icon="boat" value={String(dashboard.active_shipments)} ar="شحنات نشطة" en="Active shipments" tone="green" onPress={() => router.push('/shipments')} />
        <MetricCard icon="receipt" value={String(dashboard.open_orders)} ar="طلبات مفتوحة" en="Open orders" tone="red" onPress={() => router.push('/more/orders')} />
      </View>

      {dashboard.current_project && <><SectionHeader ar="المشروع الحالي" en="Current project" /><Link href={{ pathname: '/project/[id]', params: { id: dashboard.current_project.id } }} asChild><Pressable><DataCard><View style={styles.head}><StatusPill ar={dashboard.current_project.status.label.ar} en={dashboard.current_project.status.label.en} tone="orange" /><Text style={styles.ref}>{dashboard.current_project.ref}</Text></View><Text style={[styles.title, { textAlign: isRtl ? 'right' : 'left' }]}>{dashboard.current_project.title}</Text><ProgressBar value={dashboard.current_project.progress_percent} /><DetailRow label={language === 'ar' ? 'نسبة الإنجاز' : 'Progress'} value={`${dashboard.current_project.progress_percent}%`} /></DataCard></Pressable></Link></>}

      {dashboard.current_shipment && <><SectionHeader ar="الشحنة الحالية" en="Current shipment" /><Link href={{ pathname: '/shipment/[id]', params: { id: dashboard.current_shipment.id } }} asChild><Pressable><DataCard><View style={styles.head}><Ionicons name="boat" size={24} color={palette.brandRed} /><Text style={styles.ref}>{dashboard.current_shipment.ref}</Text></View><Text style={[styles.title, { textAlign: isRtl ? 'right' : 'left' }]}>{dashboard.current_shipment.container_number || `${dashboard.current_shipment.container_count} containers`}</Text><DetailRow label="ETA" value={formatDate(dashboard.current_shipment.eta, language)} /><DetailRow label={language === 'ar' ? 'الوقت الحر حتى' : 'Free time end'} value={formatDate(dashboard.current_shipment.free_time_end, language)} /></DataCard></Pressable></Link></>}

      <SectionHeader ar="الأرصدة حسب العملة" en="Balances by currency" />
      {dashboard.balances.length === 0 ? <EmptyState /> : dashboard.balances.map((balance) => <DataCard key={balance.currency}><DetailRow label={balance.currency} value={balance.balance} alert={Number(balance.balance) > 0} /><Text style={styles.currencyNote}>{language === 'ar' ? 'لا تُجمع العملات المختلفة.' : 'Different currencies are never combined.'}</Text></DataCard>)}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  metrics: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 }, head: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }, ref: { color: palette.blue, fontSize: 13, fontWeight: '900' }, title: { color: palette.navy, fontSize: 17, fontWeight: '900' }, currencyNote: { color: palette.muted, fontSize: 9, textAlign: 'center' },
});
