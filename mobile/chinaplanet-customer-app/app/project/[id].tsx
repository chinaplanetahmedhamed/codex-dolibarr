import { useLocalSearchParams } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { AppScreen, BrandHeader, ProgressBar, SectionHeader, ShipmentTimeline, StatusPill } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { useResource } from '@/src/hooks/useResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';
import { palette, radii, shadows } from '@/src/theme';

export default function ProjectDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const projectId = Array.isArray(id) ? id[0] : id;
  const { language, label } = useI18n();
  const resource = useResource(() => mobileRepository.project(projectId), [projectId]);
  if (resource.loading) return <AppScreen><BrandHeader titleAr="المشروع" titleEn="Project" /><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><BrandHeader titleAr="المشروع" titleEn="Project" /><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const project = resource.data;
  if (!project) return <AppScreen><EmptyState /></AppScreen>;

  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}>
    <BrandHeader titleAr="بطاقة المشروع" titleEn="Project Center" />
    <View style={styles.hero}><View style={styles.heroHead}><StatusPill ar={project.status.label.ar} en={project.status.label.en} tone="orange" /><Text style={styles.ref}>{project.ref}</Text></View><Text style={styles.title}>{project.title}</Text><Text style={styles.description}>{project.description || '—'}</Text><View style={styles.progressHead}><Text style={styles.percent}>{project.progress_percent}%</Text><Text style={styles.progressLabel}>{language === 'ar' ? 'نسبة الإنجاز' : 'Progress'}</Text></View><ProgressBar value={project.progress_percent} /></View>
    <SectionHeader ar="الخطوات" en="Actions" />
    <DataCard><DetailRow label={language === 'ar' ? 'الخطوة الحالية' : 'Current step'} value={label(project.current_step) || '—'} /><DetailRow label={language === 'ar' ? 'الخطوة التالية' : 'Next step'} value={label(project.next_step) || '—'} /><DetailRow label={language === 'ar' ? 'الموعد المتوقع' : 'Expected date'} value={formatDate(project.expected_next_at, language)} /><DetailRow label={language === 'ar' ? 'آخر تحديث' : 'Last update'} value={formatDate(project.latest_update_at, language)} /></DataCard>
    <SectionHeader ar="مراحل المشروع" en="Project timeline" />
    {project.timeline.length ? <ShipmentTimeline steps={project.timeline} /> : <EmptyState />}
    <SectionHeader ar="الروابط المتاحة" en="Related records" />
    <DataCard><DetailRow label={language === 'ar' ? 'الشحنات' : 'Shipments'} value={String(project.shipment_ids.length)} /><DetailRow label={language === 'ar' ? 'الملفات' : 'Documents'} value={String(project.document_ids.length)} /><DetailRow label={language === 'ar' ? 'التقارير' : 'Reports'} value={String(project.report_ids.length)} /></DataCard>
    <SectionHeader ar="الأرصدة المرتبطة" en="Related balances" />
    {project.balances.length ? project.balances.map((balance) => <DataCard key={balance.currency}><DetailRow label={balance.currency} value={balance.balance} /></DataCard>) : <EmptyState />}
  </AppScreen>;
}

const styles = StyleSheet.create({
  hero: { backgroundColor: palette.navy, borderRadius: radii.large, padding: 20, gap: 10, ...shadows.card }, heroHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }, ref: { color: palette.gold, fontSize: 13, fontWeight: '900' }, title: { color: palette.white, fontSize: 23, lineHeight: 32, fontWeight: '900', textAlign: 'right' }, description: { color: '#C9DDE6', fontSize: 12, lineHeight: 20, textAlign: 'right' }, progressHead: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 }, percent: { color: palette.gold, fontWeight: '900' }, progressLabel: { color: palette.white, fontSize: 11, fontWeight: '700' },
});
