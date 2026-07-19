import { useLocalSearchParams } from 'expo-router';
import { Text } from 'react-native';

import { AppScreen, BrandHeader, SectionHeader } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { useResource } from '@/src/hooks/useResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { commercialDocumentTypeLabel } from '@/src/i18n/domainLabels';
import { mobileRepository } from '@/src/repositories/mobileRepository';

export default function CommercialDocumentDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>(); const documentId = Array.isArray(id) ? id[0] : id;
  const { language } = useI18n(); const resource = useResource(() => mobileRepository.commercialDocument(documentId), [documentId]);
  if (resource.loading) return <AppScreen><LoadingState /></AppScreen>; if (resource.error) return <AppScreen><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>; const document = resource.data; if (!document) return <AppScreen><EmptyState /></AppScreen>;
  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}><BrandHeader titleAr="المستند التجاري" titleEn="Commercial Document" /><DataCard><DetailRow label={document.ref} value={commercialDocumentTypeLabel(document.document_type, language)} /><DetailRow label={language === 'ar' ? 'التاريخ' : 'Date'} value={formatDate(document.document_date, language)} /><DetailRow label={language === 'ar' ? 'المستلم' : 'Consignee'} value={document.consignee || '—'} /><DetailRow label="Incoterm" value={document.incoterm || '—'} /><DetailRow label={language === 'ar' ? 'المسار' : 'Route'} value={`${document.port_loading || '—'} → ${document.port_destination || '—'}`} />{document.public_notes && <Text>{document.public_notes}</Text>}</DataCard><SectionHeader ar="البنود" en="Lines" />{document.lines.length === 0 ? <EmptyState /> : document.lines.map((line) => <DataCard key={line.position}><DetailRow label={`${line.position}. ${line.description}`} value={`${line.quantity} ${line.unit || ''}`} /><DetailRow label={language === 'ar' ? 'الإجمالي' : 'Total'} value={`${line.total.amount} ${line.total.currency}`} /><DetailRow label="HS" value={line.hs_code || '—'} /></DataCard>)}</AppScreen>;
}
