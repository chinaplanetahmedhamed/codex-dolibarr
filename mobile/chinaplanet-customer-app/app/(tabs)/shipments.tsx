import { Link } from 'expo-router';
import { Pressable, Text } from 'react-native';

import { AppScreen, BrandHeader, Hero } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { CardHeader, DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { PaginationFooter } from '@/src/components/PaginationFooter';
import { usePaginatedResource } from '@/src/hooks/usePaginatedResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';
import { palette } from '@/src/theme';

export default function ShipmentsScreen() {
  const { language } = useI18n();
  const resource = usePaginatedResource((cursor) => mobileRepository.shipments({ limit: 25, cursor }));
  if (resource.loading) return <AppScreen><BrandHeader titleAr="الشحنات" titleEn="Shipments" /><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><BrandHeader titleAr="الشحنات" titleEn="Shipments" /><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const shipments = resource.items;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="الشحنات" titleEn="Shipments" /><Hero eyebrow="SHIPMENT TRACKING" title={language === 'ar' ? 'تتبّع شحناتك' : 'Track shipments'} subtitle={language === 'ar' ? 'الوصول والوقت الحر والمستندات منفصلة وواضحة.' : 'Arrival, free time and documents remain clear and separate.'} icon="boat" />{shipments.length === 0 ? <EmptyState /> : shipments.map((shipment) => <Link key={shipment.id} href={{ pathname: '/shipment/[id]', params: { id: shipment.id } }} asChild><Pressable><DataCard><CardHeader title={shipment.ref} status={shipment.status} trailing={<Text style={{ color: palette.brandRed, fontWeight: '900' }}>{shipment.container_count}</Text>} /><DetailRow label={language === 'ar' ? 'الحاوية' : 'Container'} value={shipment.container_number || '—'} /><DetailRow label="BL" value={shipment.bl_number || '—'} /><DetailRow label="ETA" value={formatDate(shipment.eta, language)} /><DetailRow label={language === 'ar' ? 'الوصول الفعلي ATA' : 'Actual arrival ATA'} value={formatDate(shipment.ata, language)} /><DetailRow label={language === 'ar' ? 'نهاية الوقت الحر' : 'Free-time end'} value={formatDate(shipment.free_time_end, language)} /></DataCard></Pressable></Link>)}<PaginationFooter hasMore={resource.hasMore} loading={resource.loadingMore} onPress={resource.loadMore} /></AppScreen>;
}
