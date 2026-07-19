import { AppScreen, BrandHeader, Hero } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { DownloadButton } from '@/src/components/DownloadButton';
import { PaginationFooter } from '@/src/components/PaginationFooter';
import { usePaginatedResource } from '@/src/hooks/usePaginatedResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';

export default function DocumentsScreen() {
  const { language } = useI18n();
  const resource = usePaginatedResource((cursor) => mobileRepository.documents({ limit: 25, cursor }));
  if (resource.loading) return <AppScreen><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const documents = resource.items;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="المستندات" titleEn="Documents" /><Hero eyebrow="APPROVED FILES" title={language === 'ar' ? 'ملفاتك الآمنة' : 'Your secure files'} subtitle={language === 'ar' ? 'تظهر النسخ المعتمدة والحالية فقط.' : 'Only current approved files are shown.'} icon="documents" />{documents.length === 0 ? <EmptyState /> : documents.map((document) => <DataCard key={document.id}><DetailRow label={document.name} value={`${Math.ceil(document.size_bytes / 1024)} KB`} /><DetailRow label={language === 'ar' ? 'النوع' : 'Type'} value={document.mime_type} /><DetailRow label={language === 'ar' ? 'الاعتماد' : 'Approved'} value={formatDate(document.approved_at, language)} /><DownloadButton target={{ kind: 'document', id: document.id }} filename={document.name} /></DataCard>)}<PaginationFooter hasMore={resource.hasMore} loading={resource.loadingMore} onPress={resource.loadMore} /></AppScreen>;
}
