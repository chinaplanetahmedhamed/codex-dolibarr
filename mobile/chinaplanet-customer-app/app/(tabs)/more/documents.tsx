import { AppScreen, BrandHeader, Hero } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { ShipmentGalleryItem } from '@/src/components/ShipmentGalleryItem';
import { PaginationFooter } from '@/src/components/PaginationFooter';
import { usePaginatedResource } from '@/src/hooks/usePaginatedResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';

export default function DocumentsScreen() {
  const { language } = useI18n();
  const resource = usePaginatedResource((cursor) => mobileRepository.shipmentDocuments({ limit: 25, cursor }));
  if (resource.loading) return <AppScreen><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const documents = resource.items;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="معارض الشحنات" titleEn="Shipment Galleries" /><Hero eyebrow="CUSTOMER-PUBLISHED SHIPMENT FILES" title={language === 'ar' ? 'صور وملفات شحناتك' : 'Your shipment photos & files'} subtitle={language === 'ar' ? 'تظهر فقط الملفات التي نشرها فريق كوكب الصين لك.' : 'Only files published to you by the China Planet team are shown.'} icon="images" />{documents.length === 0 ? <EmptyState /> : documents.map((document) => <ShipmentGalleryItem item={document} key={document.id} />)}<PaginationFooter hasMore={resource.hasMore} loading={resource.loadingMore} onPress={resource.loadMore} /></AppScreen>;
}
