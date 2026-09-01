import { Link } from 'expo-router';
import { Pressable, Text } from 'react-native';

import { AppScreen, BrandHeader, Hero } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { ShipmentGalleryItem } from '@/src/components/ShipmentGalleryItem';
import { DataCard, DetailRow } from '@/src/components/DataCard';
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
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="المستندات المرتبطة" titleEn="Linked Documents" /><Hero eyebrow="CUSTOMER-PUBLISHED DOCUMENTS" title={language === 'ar' ? 'كل مستنداتك' : 'All your documents'} subtitle={language === 'ar' ? 'المستندات التجارية ومستندات المشاريع والعمليات والشحن التي نُشرت لك فقط.' : 'Only commercial, project, operations and shipment documents published to your account.'} icon="documents" />{documents.length === 0 ? <EmptyState /> : documents.map((document) => document.source_type === 'commercial_document' ? <Link key={document.id} href={{ pathname: '/more/commercial-document/[id]', params: { id: document.id } }} asChild><Pressable accessibilityRole="button"><DataCard><DetailRow label={language === 'ar' ? 'مستند تجاري' : 'Commercial document'} value={document.name} /><DetailRow label={language === 'ar' ? 'المشروع' : 'Project'} value={document.project_ref ? `${document.project_ref}${document.project_title ? ` · ${document.project_title}` : ''}` : (language === 'ar' ? 'غير مرتبط بمشروع' : 'No related project')} />{document.shipment_ref ? <DetailRow label={language === 'ar' ? 'الشحنة' : 'Shipment'} value={document.shipment_ref} /> : null}<Text>{language === 'ar' ? 'عرض التفاصيل' : 'View details'}</Text></DataCard></Pressable></Link> : <ShipmentGalleryItem item={document} key={document.id} />)}<PaginationFooter hasMore={resource.hasMore} loading={resource.loadingMore} onPress={resource.loadMore} /></AppScreen>;
}
