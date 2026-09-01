import { Link } from 'expo-router';
import { Pressable, Text } from 'react-native';

import { AppScreen, BrandHeader, Hero } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { CardHeader, DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { PaginationFooter } from '@/src/components/PaginationFooter';
import { usePaginatedResource } from '@/src/hooks/usePaginatedResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';

export default function ServiceInvoicesScreen() {
  const { language } = useI18n();
  const resource = usePaginatedResource((cursor) => mobileRepository.invoices({ limit: 25, cursor }));
  if (resource.loading) return <AppScreen><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const invoices = resource.items;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="فواتير الخدمات" titleEn="Service Invoices" /><Hero eyebrow="READ ONLY INVOICES" title={language === 'ar' ? 'فواتير الخدمات' : 'Service invoices'} subtitle={language === 'ar' ? 'الإجمالي والمدفوع والمتبقي حسب العملة، مع تفاصيل البنود.' : 'Total, paid and remaining by original currency, with line details.'} icon="document-text" />{invoices.length === 0 ? <EmptyState /> : invoices.map((invoice) => <Link key={invoice.id} href={{ pathname: '/more/service-invoice/[id]', params: { id: invoice.id } }} asChild><Pressable accessibilityRole="button"><DataCard><CardHeader title={invoice.ref} status={invoice.status} /><DetailRow label={language === 'ar' ? 'الإصدار' : 'Issued'} value={formatDate(invoice.issued_at, language)} /><DetailRow label={language === 'ar' ? 'الاستحقاق' : 'Due'} value={formatDate(invoice.due_at, language)} /><DetailRow label={language === 'ar' ? 'الإجمالي' : 'Total'} value={`${invoice.total.amount} ${invoice.total.currency}`} /><DetailRow label={language === 'ar' ? 'المدفوع أو الممول' : 'Paid or funded'} value={`${invoice.paid.amount} ${invoice.paid.currency}`} /><DetailRow label={language === 'ar' ? 'المتبقي' : 'Remaining'} value={`${invoice.remaining.amount} ${invoice.remaining.currency}`} alert={Number(invoice.remaining.amount) > 0} /><Text>{language === 'ar' ? 'عرض التفاصيل والبنود' : 'View details and line items'}</Text></DataCard></Pressable></Link>)}<PaginationFooter hasMore={resource.hasMore} loading={resource.loadingMore} onPress={resource.loadMore} /></AppScreen>;
}
