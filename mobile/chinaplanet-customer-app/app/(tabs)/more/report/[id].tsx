import { useLocalSearchParams } from 'expo-router';
import { Text } from 'react-native';

import { AppScreen, BrandHeader, ProgressBar, SectionHeader } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { DownloadButton } from '@/src/components/DownloadButton';
import { useResource } from '@/src/hooks/useResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';

export default function ReportDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>(); const reportId = Array.isArray(id) ? id[0] : id;
  const { language, label } = useI18n(); const resource = useResource(() => mobileRepository.report(reportId), [reportId]);
  if (resource.loading) return <AppScreen><LoadingState /></AppScreen>; if (resource.error) return <AppScreen><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>; const report = resource.data; if (!report) return <AppScreen><EmptyState /></AppScreen>;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="التقرير" titleEn="Report" /><DataCard><DetailRow label={report.ref} value={report.title} /><DetailRow label={language === 'ar' ? 'المرحلة' : 'Stage'} value={label(report.stage.label)} /><ProgressBar value={report.progress_percent} /><DetailRow label={language === 'ar' ? 'التقدم' : 'Progress'} value={`${report.progress_percent}%`} /><DetailRow label={language === 'ar' ? 'آخر تحديث' : 'Updated'} value={formatDate(report.updated_at, language)} />{report.public_note && <Text>{report.public_note}</Text>}</DataCard><SectionHeader ar="حقول التقرير" en="Report fields" />{report.fields.length === 0 ? <EmptyState /> : report.fields.map((field) => <DataCard key={field.code}><DetailRow label={label(field.label)} value={field.value} /></DataCard>)}<SectionHeader ar="ملفات التقرير" en="Report documents" />{report.documents.length === 0 ? <EmptyState /> : report.documents.map((document) => <DataCard key={document.id}><DetailRow label={document.name} value={document.mime_type} /><DownloadButton target={{ kind: 'report-document', reportId: report.id, documentId: document.id }} filename={document.name} /></DataCard>)}</AppScreen>;
}
