import { useLocalSearchParams } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { AppScreen, BrandHeader, SectionHeader, ShipmentTimeline, StatusPill } from '@/src/components';
import { EmptyState, ErrorState, LoadingState } from '@/src/components/AsyncState';
import { DataCard, DetailRow, formatDate } from '@/src/components/DataCard';
import { useResource } from '@/src/hooks/useResource';
import { useI18n } from '@/src/i18n/I18nProvider';
import { mobileRepository } from '@/src/repositories/mobileRepository';
import { palette, radii, shadows } from '@/src/theme';

export default function ShipmentDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const shipmentId = Array.isArray(id) ? id[0] : id;
  const { language, label } = useI18n();
  const resource = useResource(() => mobileRepository.shipment(shipmentId), [shipmentId]);
  if (resource.loading) return <AppScreen><BrandHeader titleAr="الشحنة" titleEn="Shipment" /><LoadingState /></AppScreen>;
  if (resource.error) return <AppScreen><BrandHeader titleAr="الشحنة" titleEn="Shipment" /><ErrorState error={resource.error} retry={resource.retry} /></AppScreen>;
  const shipment = resource.data;
  if (!shipment) return <AppScreen><EmptyState /></AppScreen>;

  return <AppScreen refreshing={resource.refreshing} onRefresh={resource.refresh}>
    <BrandHeader titleAr="بطاقة الشحنة" titleEn="Shipment Card" />
    <View style={styles.hero}><View style={styles.heroHead}><StatusPill ar={shipment.status.label.ar} en={shipment.status.label.en} tone="green" /><Text style={styles.ref}>{shipment.ref}</Text></View><Text style={styles.container}>{shipment.container_number || '—'} · {shipment.container_type || '—'}</Text><Text style={styles.updated}>{language === 'ar' ? 'آخر تحديث' : 'Updated'}: {formatDate(shipment.updated_at, language)}</Text></View>
    {shipment.eta_changed && <View style={styles.alert}><Text style={styles.alertTitle}>{language === 'ar' ? 'تم تغيير موعد الوصول المتوقع' : 'ETA was changed'}</Text><Text style={styles.alertText}>{formatDate(shipment.previous_eta, language)} → {formatDate(shipment.eta, language)} ({shipment.eta_change_days ?? '—'})</Text>{shipment.eta_change?.public_reason && <Text style={styles.alertText}>{shipment.eta_change.public_reason}</Text>}</View>}
    <SectionHeader ar="الوصول والوقت الحر" en="Arrival & free time" />
    <DataCard><DetailRow label="ETD" value={formatDate(shipment.etd, language)} /><DetailRow label="ATD" value={formatDate(shipment.atd, language)} /><DetailRow label="ETA" value={formatDate(shipment.eta, language)} /><DetailRow label="ATA" value={formatDate(shipment.ata, language)} /><DetailRow label={language === 'ar' ? 'بداية الوقت الحر' : 'Free-time start'} value={formatDate(shipment.free_time_start, language)} /><DetailRow label={language === 'ar' ? 'نهاية الوقت الحر' : 'Free-time end'} value={formatDate(shipment.free_time_end, language)} /></DataCard>
    {shipment.latest_update && <><SectionHeader ar="آخر تحديث عام" en="Latest public update" /><DataCard><Text style={styles.publicTitle}>{label(shipment.latest_update.title)}</Text>{shipment.latest_update.public_note && <Text style={styles.publicNote}>{shipment.latest_update.public_note}</Text>}<DetailRow label={label(shipment.latest_update.source)} value={formatDate(shipment.latest_update.occurred_at, language)} /></DataCard></>}
    {shipment.customer_action?.required_from_customer && <><SectionHeader ar="إجراء مطلوب منك" en="Action required" /><View style={styles.action}><Text style={styles.publicTitle}>{label(shipment.customer_action.label)}</Text>{shipment.customer_action.public_note && <Text style={styles.publicNote}>{shipment.customer_action.public_note}</Text>}<DetailRow label={language === 'ar' ? 'الموعد' : 'Due'} value={formatDate(shipment.customer_action.due_at, language)} /></View></>}
    <SectionHeader ar="رحلة الشحنة" en="Shipment timeline" />
    {shipment.timeline.length ? <ShipmentTimeline steps={shipment.timeline} /> : <EmptyState />}
    <SectionHeader ar="المستندات المطلوبة" en="Document requirements" />
    {shipment.document_requirements.length ? shipment.document_requirements.map((requirement) => <DataCard key={requirement.code}><DetailRow label={label(requirement.label)} value={label(requirement.status_label)} alert={requirement.action_required} />{requirement.public_note && <Text style={styles.publicNote}>{requirement.public_note}</Text>}</DataCard>) : <EmptyState />}
    <SectionHeader ar="تفاصيل الرحلة" en="Voyage details" />
    <DataCard><DetailRow label={language === 'ar' ? 'الحجز' : 'Booking'} value={shipment.booking_number || '—'} /><DetailRow label={language === 'ar' ? 'الخط الملاحي' : 'Shipping line'} value={shipment.shipping_line || '—'} /><DetailRow label={language === 'ar' ? 'السفينة' : 'Vessel'} value={shipment.vessel_name || '—'} /><DetailRow label={language === 'ar' ? 'الرحلة' : 'Voyage'} value={shipment.voyage_number || '—'} /><DetailRow label={language === 'ar' ? 'ميناء الشحن' : 'Port of loading'} value={shipment.port_loading || '—'} /><DetailRow label={language === 'ar' ? 'ميناء الوصول' : 'Destination port'} value={shipment.port_destination || '—'} /></DataCard>
  </AppScreen>;
}

const styles = StyleSheet.create({
  hero: { backgroundColor: palette.navy, borderRadius: radii.large, padding: 20, gap: 10, ...shadows.card }, heroHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }, ref: { color: palette.gold, fontSize: 19, fontWeight: '900' }, container: { color: palette.white, fontSize: 15, fontWeight: '900', textAlign: 'right' }, updated: { color: '#BFD5DE', fontSize: 10, textAlign: 'right' }, alert: { backgroundColor: palette.orangeSoft, borderRadius: radii.medium, borderWidth: 1, borderColor: palette.gold, padding: 16, gap: 6 }, alertTitle: { color: '#795000', fontSize: 14, fontWeight: '900', textAlign: 'right' }, alertText: { color: '#8A620F', fontSize: 11, lineHeight: 18, textAlign: 'right' }, publicTitle: { color: palette.navy, fontSize: 14, fontWeight: '900', textAlign: 'right' }, publicNote: { color: palette.text, fontSize: 12, lineHeight: 20, textAlign: 'right' }, action: { backgroundColor: '#FFF9E9', borderWidth: 1, borderColor: palette.gold, borderRadius: radii.medium, padding: 16, gap: 10 },
});
