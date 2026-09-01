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
import { ReportSummary } from '@/src/api/types';

export default function ReportsScreen() {
  const { language } = useI18n(); const resource = usePaginatedResource((cursor) => mobileRepository.reports({ limit: 25, cursor }));
  if (resource.loading) return <AppScreen><LoadingState /></AppScreen>; if (resource.error) return <AppScreen><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>; const reports = resource.items;
  return <LocalizedReportList hasMore={resource.hasMore} language={language} loadMore={resource.loadMore} loadingMore={resource.loadingMore} onRefresh={resource.refresh} refreshing={resource.refreshing} reports={reports} />;
}

function LocalizedReportList({ reports, language, refreshing, onRefresh, hasMore, loadingMore, loadMore }: { reports: ReportSummary[]; language: 'ar' | 'en'; refreshing: boolean; onRefresh: () => void; hasMore: boolean; loadingMore: boolean; loadMore: () => void }) {
  const resultLabel=(value?:string)=>{const labels:Record<string,[string,string]>={pass:['ناجح','Pass'],fail:['راسب','Fail'],pending_corrective_action:['بانتظار الإجراء التصحيحي','Pending corrective action'],reinspection_required:['إعادة فحص مطلوبة','Reinspection required']};const pair=labels[value||'']||['—','—'];return pair[language==='ar'?0:1];};
  return <AppScreen refreshing={refreshing} onRefresh={onRefresh}><BrandHeader titleAr="التقارير" titleEn="Reports" /><Hero eyebrow="APPROVED REPORTS" title={language === 'ar' ? 'تقارير الجودة والخدمات' : 'Quality & service reports'} subtitle={language === 'ar' ? 'التقارير العامة المعتمدة والمرئية لك فقط.' : 'Only approved reports visible to your account.'} icon="clipboard" />{reports.length === 0 ? <EmptyState /> : reports.map((report) => <Link key={report.id} href={{ pathname: '/more/report/[id]', params: { id: report.id } }} asChild><Pressable accessibilityLabel={`${report.ref} ${report.title}`}><DataCard><CardHeader title={`${report.ref} · ${report.title}`} status={report.stage} /><DetailRow label={language === 'ar' ? 'نوع التقرير' : 'Report type'} value={reportTypeLabel(report.report_type, language)} />{report.report_type === 'quality_control' ? <><DetailRow label={language === 'ar' ? 'النتيجة النهائية' : 'Overall result'} value={resultLabel(report.overall_result)} /><DetailRow label={language === 'ar' ? 'الإصدار' : 'Version'} value={`v${report.publication_version || 1}`} /><DetailRow label={language === 'ar' ? 'تاريخ الاعتماد' : 'Published'} value={formatDate(report.published_at, language)} /></> : <><ProgressBar value={report.progress_percent} /><DetailRow label={language === 'ar' ? 'التقدم' : 'Progress'} value={`${report.progress_percent}%`} /><DetailRow label={language === 'ar' ? 'الإجراء التالي' : 'Next action'} value={report.next_action || '—'} /><DetailRow label={language === 'ar' ? 'الموعد' : 'Due'} value={formatDate(report.due_at, language)} /></>}</DataCard></Pressable></Link>)}<PaginationFooter hasMore={hasMore} loading={loadingMore} onPress={loadMore} /></AppScreen>;
}
