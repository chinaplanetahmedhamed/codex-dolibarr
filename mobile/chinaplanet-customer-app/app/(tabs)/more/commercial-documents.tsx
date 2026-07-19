import { Link } from 'expo-router';
import { Pressable } from 'react-native';

import { AppScreen, BrandHeader, Hero } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { PaginationFooter } from '@/src/components/PaginationFooter';
import { usePaginatedResource } from '@/src/hooks/usePaginatedResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { commercialDocumentTypeLabel } from '@/src/i18n/domainLabels';
import { mobileRepository } from '@/src/repositories/mobileRepository';

export default function CommercialDocumentsScreen() {
  const { language } = useI18n();
  const resource = usePaginatedResource((cursor) => mobileRepository.commercialDocuments({ limit: 25, cursor }));
  if (resource.loading) return <AppScreen><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const documents = resource.items;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="المستندات التجارية" titleEn="Commercial Documents" /><Hero eyebrow="COMMERCIAL DOCUMENTS" title={language === 'ar' ? 'مستندات العمليات' : 'Operation documents'} subtitle={language === 'ar' ? 'الفواتير وقوائم التعبئة والبوالص المعتمدة.' : 'Approved invoices, packing lists and bills of lading.'} icon="reader" />{documents.length === 0 ? <EmptyState /> : documents.map((document) => <Link key={document.id} href={{ pathname: '/more/commercial-document/[id]', params: { id: document.id } }} asChild><Pressable><DataCard><DetailRow label={document.ref} value={commercialDocumentTypeLabel(document.document_type, language)} /><DetailRow label={language === 'ar' ? 'التاريخ' : 'Date'} value={formatDate(document.document_date, language)} /><DetailRow label={language === 'ar' ? 'العملة' : 'Currency'} value={document.currency || '—'} /></DataCard></Pressable></Link>)}<PaginationFooter hasMore={resource.hasMore} loading={resource.loadingMore} onPress={resource.loadMore} /></AppScreen>;
}
