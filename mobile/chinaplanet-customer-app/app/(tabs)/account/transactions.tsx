import { AppScreen, BrandHeader, Hero } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { PaginationFooter } from '@/src/components/PaginationFooter';
import { usePaginatedResource } from '@/src/hooks/usePaginatedResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { transactionDirectionLabel } from '@/src/i18n/domainLabels';
import { mobileRepository } from '@/src/repositories/mobileRepository';

export default function TransactionsScreen() {
  const { language } = useI18n();
  const resource = usePaginatedResource((cursor) => mobileRepository.transactions({ limit: 25, cursor }));
  if (resource.loading) return <AppScreen><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const transactions = resource.items;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="كشف الحساب" titleEn="Transactions" /><Hero eyebrow="ACCOUNT STATEMENT" title={language === 'ar' ? 'حركات الحساب' : 'Account activity'} subtitle={language === 'ar' ? 'كل حركة تحتفظ بعملتها الأصلية.' : 'Every transaction preserves its original currency.'} icon="swap-horizontal" />{transactions.length === 0 ? <EmptyState /> : transactions.map((transaction) => <DataCard key={transaction.id}><DetailRow label={transaction.description} value={`${transaction.amount.amount} ${transaction.amount.currency}`} alert={transaction.direction === 'debit'} /><DetailRow label={language === 'ar' ? 'الاتجاه' : 'Direction'} value={transactionDirectionLabel(transaction.direction, language)} /><DetailRow label={language === 'ar' ? 'المرجع' : 'Reference'} value={transaction.reference || '—'} /><DetailRow label={language === 'ar' ? 'التاريخ' : 'Date'} value={formatDate(transaction.occurred_at, language)} /></DataCard>)}<PaginationFooter hasMore={resource.hasMore} loading={resource.loadingMore} onPress={resource.loadMore} /></AppScreen>;
}
