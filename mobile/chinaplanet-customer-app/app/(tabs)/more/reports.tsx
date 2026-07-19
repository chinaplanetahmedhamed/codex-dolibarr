import { Link } from 'expo-router';
import { Pressable } from 'react-native';

import { AppScreen, BrandHeader, Hero, ProgressBar } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { CardHeader, DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { PaginationFooter } from '@/src/components/PaginationFooter';
import { usePaginatedResource } from '@/src/hooks/usePaginatedResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { reportTypeLabel } from '@/src/i18n/domainLabels';
import { mobileRepository } from '@/src/repositories/mobileRepository';

export default function ReportsScreen() {
  const { language } = useI18n(); const resource = usePaginatedResource((cursor) => mobileRepository.reports({ limit: 25, cursor }));
  if (resource.loading) return <AppScreen><LoadingState /></AppScreen>; if (resource.error) return <AppScreen><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>; const reports = resource.items;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="التقارير" titleEn="Reports" /><Hero eyebrow="APPROVED REPORTS" title={language === 'ar' ? 'تقارير الجودة والخدمات' : 'Quality & service reports'} subtitle={language === 'ar' ? 'التقارير العامة المعتمدة والمرئية لك فقط.' : 'Only approved reports visible to your account.'} icon="clipboard" />{reports.length === 0 ? <EmptyState /> : reports.map((report) => <Link key={report.id} href={{ pathname: '/more/report/[id]', params: { id: report.id } }} asChild><Pressable><DataCard><CardHeader title={`${report.ref} · ${report.title}`} status={report.stage} /><ProgressBar value={report.progress_percent} /><DetailRow label={language === 'ar' ? 'نوع التقرير' : 'Report type'} value={reportTypeLabel(report.report_type, language)} /><DetailRow label={language === 'ar' ? 'التقدم' : 'Progress'} value={`${report.progress_percent}%`} /><DetailRow label={language === 'ar' ? 'الإجراء التالي' : 'Next action'} value={report.next_action || '—'} /><DetailRow label={language === 'ar' ? 'الموعد' : 'Due'} value={formatDate(report.due_at, language)} /></DataCard></Pressable></Link>)}<PaginationFooter hasMore={resource.hasMore} loading={resource.loadingMore} onPress={resource.loadMore} /></AppScreen>;
}
