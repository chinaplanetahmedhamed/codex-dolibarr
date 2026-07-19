import { AppScreen, BrandHeader, Hero } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { CardHeader, DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { PaginationFooter } from '@/src/components/PaginationFooter';
import { usePaginatedResource } from '@/src/hooks/usePaginatedResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';

export default function OrdersScreen() {
  const { language } = useI18n();
  const resource = usePaginatedResource((cursor) => mobileRepository.orders({ limit: 25, cursor }));
  if (resource.loading) return <AppScreen><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const orders = resource.items;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="الطلبات" titleEn="Orders" /><Hero eyebrow="CUSTOMER ORDERS" title={language === 'ar' ? 'طلباتك' : 'Your orders'} subtitle={language === 'ar' ? 'قيم الطلبات والمدفوع والمتبقي للقراءة فقط.' : 'Order totals, paid and remaining values are read-only.'} icon="receipt" />{orders.length === 0 ? <EmptyState /> : orders.map((order) => <DataCard key={order.id}><CardHeader title={order.ref} status={order.status} /><DetailRow label={language === 'ar' ? 'التاريخ' : 'Date'} value={formatDate(order.ordered_at, language)} /><DetailRow label={language === 'ar' ? 'الإجمالي' : 'Total'} value={`${order.total.amount} ${order.total.currency}`} /><DetailRow label={language === 'ar' ? 'المدفوع' : 'Paid'} value={`${order.paid.amount} ${order.paid.currency}`} /><DetailRow label={language === 'ar' ? 'المتبقي' : 'Remaining'} value={`${order.remaining.amount} ${order.remaining.currency}`} alert={Number(order.remaining.amount) > 0} /></DataCard>)}<PaginationFooter hasMore={resource.hasMore} loading={resource.loadingMore} onPress={resource.loadMore} /></AppScreen>;
}
