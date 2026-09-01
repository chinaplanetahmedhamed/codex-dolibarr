import { useLocalSearchParams } from 'expo-router';
import { Text } from 'react-native';

import { AppScreen, BrandHeader, SectionHeader } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { CardHeader, DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { useResource } from '@/src/hooks/useResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';

export default function ServiceInvoiceDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const invoiceId = Array.isArray(id) ? id[0] : id;
  const { language } = useI18n();
  const resource = useResource(() => mobileRepository.invoice(invoiceId), [invoiceId]);
  if (resource.loading) return <AppScreen><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const invoice = resource.data;
  if (!invoice) return <AppScreen><EmptyState /></AppScreen>;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}>
    <BrandHeader titleAr="تفاصيل الفاتورة" titleEn="Invoice Details" />
    <DataCard>
      <CardHeader title={invoice.ref} status={invoice.status} />
      <DetailRow label={language === 'ar' ? 'الإصدار' : 'Issued'} value={formatDate(invoice.issued_at, language)} />
      <DetailRow label={language === 'ar' ? 'الاستحقاق' : 'Due'} value={formatDate(invoice.due_at, language)} />
      <DetailRow label={language === 'ar' ? 'طلب العميل' : 'Customer order'} value={invoice.order_ref || '—'} />
      <DetailRow label={language === 'ar' ? 'قبل الضريبة' : 'Subtotal'} value={`${invoice.subtotal.amount} ${invoice.subtotal.currency}`} />
      <DetailRow label={language === 'ar' ? 'الخصم' : 'Discount'} value={`${invoice.discount.amount} ${invoice.discount.currency}`} />
      <DetailRow label={language === 'ar' ? 'الضريبة' : 'Tax'} value={`${invoice.tax.amount} ${invoice.tax.currency}`} />
      <DetailRow label={language === 'ar' ? 'الإجمالي' : 'Total'} value={`${invoice.total.amount} ${invoice.total.currency}`} />
      <DetailRow label={language === 'ar' ? 'دفعات مباشرة' : 'Direct payments'} value={`${invoice.direct_paid.amount} ${invoice.direct_paid.currency}`} />
      <DetailRow label={language === 'ar' ? 'رصيد مطبق' : 'Applied credit'} value={`${invoice.applied_credit.amount} ${invoice.applied_credit.currency}`} />
      <DetailRow label={language === 'ar' ? 'المتبقي' : 'Outstanding'} value={`${invoice.remaining.amount} ${invoice.remaining.currency}`} alert={Number(invoice.remaining.amount) > 0} />
      {invoice.public_note ? <Text>{invoice.public_note}</Text> : null}
    </DataCard>
    <SectionHeader ar="البنود" en="Line items" />
    {invoice.lines.length === 0 ? <EmptyState /> : invoice.lines.map((line, index) => <DataCard key={`${index}-${line.description}`}><DetailRow label={`${index + 1}. ${line.description}`} value={`${line.quantity} ${line.unit || ''}`} /><DetailRow label={language === 'ar' ? 'سعر الوحدة' : 'Unit price'} value={`${line.unit_price.amount} ${line.unit_price.currency}`} /><DetailRow label={language === 'ar' ? 'الضريبة' : 'Tax'} value={`${line.tax_rate}%`} /><DetailRow label={language === 'ar' ? 'الإجمالي' : 'Total'} value={`${line.total.amount} ${line.total.currency}`} /></DataCard>)}
    <SectionHeader ar="الدفعات المرتبطة" en="Linked payments" />
    {invoice.payments.length === 0 ? <EmptyState /> : invoice.payments.map((payment, index) => <DataCard key={`${index}-${payment.reference}`}><DetailRow label={language === 'ar' ? 'التاريخ' : 'Date'} value={formatDate(payment.date, language)} /><DetailRow label={language === 'ar' ? 'المرجع' : 'Reference'} value={payment.reference} /><DetailRow label={language === 'ar' ? 'المبلغ' : 'Amount'} value={`${payment.amount.amount} ${payment.amount.currency}`} />{payment.public_note ? <DetailRow label={language === 'ar' ? 'ملاحظة الدفع' : 'Payment note'} value={payment.public_note} /> : null}</DataCard>)}
  </AppScreen>;
}
