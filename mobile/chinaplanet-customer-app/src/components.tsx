import { Ionicons } from '@expo/vector-icons';
import { useNetInfo } from '@react-native-community/netinfo';
import { PropsWithChildren, ReactNode } from 'react';
import {
  Pressable,
  Image,
  RefreshControl,
  ScrollView,
  StyleProp,
  StyleSheet,
  Text,
  View,
  ViewStyle,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { TimelineStep } from '@/src/api/types';
import { useI18n } from '@/src/i18n/I18nProvider';
import { contentAlignment, leadingRowDirection, textAlignment } from '@/src/i18n/layout';
import { palette, radii, shadows } from '@/src/theme';

type IconName = keyof typeof Ionicons.glyphMap;

export function AppScreen({ children, contentStyle, refreshing = false, onRefresh }: PropsWithChildren<{ contentStyle?: StyleProp<ViewStyle>; refreshing?: boolean; onRefresh?: () => void }>) {
  const network = useNetInfo();
  const { isRtl } = useI18n();
  const offline = network.isConnected === false || network.isInternetReachable === false;
  return (
    <SafeAreaView edges={['top']} style={styles.safeArea}>
      <ScrollView
        contentContainerStyle={[styles.screen, contentStyle]}
        refreshControl={onRefresh ? <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={palette.brandRed} /> : undefined}
        showsVerticalScrollIndicator={false}>
        {offline && <View style={[styles.offlineBanner, { flexDirection: leadingRowDirection(isRtl) }]}><Ionicons name="cloud-offline-outline" size={18} color="#795000" /><Text style={styles.offlineText}>وضع عدم الاتصال · عرض آخر بيانات محفوظة / Offline · last known data</Text></View>}
        {children}
      </ScrollView>
    </SafeAreaView>
  );
}

export function BrandHeader({ titleAr, titleEn }: { titleAr?: string; titleEn?: string }) {
  const { isRtl } = useI18n();
  const align = textAlignment(isRtl);
  return (
    <View style={[styles.brandHeader, { flexDirection: leadingRowDirection(isRtl) }]}>
      <View style={styles.brandMark}>
        <Image
          accessibilityLabel="شعار كوكب الصين / China Planet logo"
          resizeMode="contain"
          source={require('../assets/images/chinaplanet-logo.png')}
          style={styles.brandMarkImage}
        />
      </View>
      <View style={[styles.brandCopy, { alignItems: contentAlignment(isRtl) }]}>
        <Text style={[styles.brandNameAr, { textAlign: align }]}>{titleAr ?? 'عملاء Vision Trading - كوكب الصين'}</Text>
        <Text style={[styles.brandNameEn, { textAlign: align }]}>{titleEn ?? 'Customer Center'}</Text>
      </View>
    </View>
  );
}

export function Hero({ eyebrow, title, subtitle, icon = 'sparkles', brandLogo = false }: { eyebrow: string; title: string; subtitle: string; icon?: IconName; brandLogo?: boolean }) {
  const { isRtl } = useI18n();
  const textAlign = isRtl ? 'right' : 'left';
  return (
    <View style={styles.hero}>
      <View style={styles.heroOrb} />
      {brandLogo ? <View style={styles.heroBrandMark}><Image accessibilityLabel="شعار كوكب الصين / China Planet logo" resizeMode="contain" source={require('../assets/images/chinaplanet-logo.png')} style={styles.heroBrandImage} /></View> : <Ionicons name={icon} size={34} color={palette.brandRedLight} />}
      <Text style={[styles.heroEyebrow, { textAlign }]}>{eyebrow}</Text>
      <Text style={[styles.heroTitle, { textAlign }]}>{title}</Text>
      <Text style={[styles.heroSubtitle, { textAlign }]}>{subtitle}</Text>
    </View>
  );
}

export function SectionHeader({ ar, en, action }: { ar: string; en: string; action?: ReactNode }) {
  const { isRtl } = useI18n();
  const align = textAlignment(isRtl);
  return (
    <View style={[styles.sectionHeader, { flexDirection: leadingRowDirection(isRtl) }]}>
      <View style={{ alignItems: contentAlignment(isRtl) }}>
        <Text style={[styles.sectionAr, { textAlign: align }]}>{ar}</Text>
        <Text style={[styles.sectionEn, { textAlign: align }]}>{en}</Text>
      </View>
      {action}
    </View>
  );
}

export function MetricCard({ icon, value, ar, en, tone = 'navy', onPress }: { icon: IconName; value: string; ar: string; en: string; tone?: 'navy' | 'green' | 'red'; onPress?: () => void }) {
  const { isRtl } = useI18n();
  const align = textAlignment(isRtl);
  const toneStyle = tone === 'green' ? styles.metricGreen : tone === 'red' ? styles.metricRed : styles.metricNavy;
  return (
    <Pressable accessibilityRole={onPress ? 'button' : undefined} accessibilityLabel={onPress ? `${ar} / ${en}` : undefined} disabled={!onPress} onPress={onPress} style={({ pressed }) => [styles.metric, toneStyle, onPress && styles.metricInteractive, pressed && styles.metricPressed]}>
      <Ionicons name={icon} size={22} color={palette.white} />
      <Text style={[styles.metricValue, { textAlign: align }]}>{value}</Text>
      <Text style={[styles.metricAr, { textAlign: align }]}>{ar}</Text>
      <Text style={[styles.metricEn, { textAlign: align }]}>{en}</Text>
    </Pressable>
  );
}

export function StatusPill({ ar, en, tone = 'green' }: { ar: string; en: string; tone?: 'green' | 'orange' | 'navy' | 'red' }) {
  const map = {
    green: [palette.greenSoft, '#17643A'],
    orange: [palette.orangeSoft, '#8A5A00'],
    navy: ['#E5EDF3', palette.navy],
    red: [palette.redSoft, '#9C302B'],
  } as const;
  return (
    <View style={[styles.pill, { backgroundColor: map[tone][0] }]}>
      <Text style={[styles.pillText, { color: map[tone][1] }]}>{ar} · {en}</Text>
    </View>
  );
}

export function ProgressBar({ value }: { value: number }) {
  return (
    <View style={styles.progressTrack}>
      <View style={[styles.progressFill, { width: `${Math.min(100, Math.max(0, value))}%` }]} />
    </View>
  );
}

export function InfoRow({ icon, ar, en, value }: { icon: IconName; ar: string; en: string; value: string }) {
  const { isRtl } = useI18n();
  const align = textAlignment(isRtl);
  return (
    <View style={[styles.infoRow, { flexDirection: leadingRowDirection(isRtl) }]}>
      <View style={styles.infoIcon}><Ionicons name={icon} size={19} color={palette.blue} /></View>
      <View style={[styles.infoCopy, { alignItems: contentAlignment(isRtl) }]}>
        <Text style={[styles.infoLabel, { textAlign: align }]}>{ar} / {en}</Text>
        <Text style={[styles.infoValue, { textAlign: align }]}>{value}</Text>
      </View>
    </View>
  );
}

export function ActionButton({ label, icon = 'arrow-back', onPress, secondary = false }: { label: string; icon?: IconName; onPress?: () => void; secondary?: boolean }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.actionButton, secondary && styles.actionSecondary, pressed && styles.pressed]}>
      <Text style={[styles.actionText, secondary && styles.actionSecondaryText]}>{label}</Text>
      <Ionicons name={icon} size={18} color={secondary ? palette.navy : palette.white} />
    </Pressable>
  );
}

export function ShipmentTimeline({ steps }: { steps: TimelineStep[] }) {
  const { isRtl, label } = useI18n();
  const align = textAlignment(isRtl);
  return (
    <View style={styles.timeline}>
      {steps.map((step, index) => {
        const isDone = step.state === 'completed';
        const isCurrent = step.state === 'current';
        const color = isDone ? palette.green : isCurrent ? palette.brandRed : '#B8C7CE';
        return (
          <View key={`${step.code}-${index}`} style={[styles.timelineRow, { flexDirection: leadingRowDirection(isRtl) }]}>
            <View style={styles.timelineRail}>
              <View style={[styles.timelineDot, { backgroundColor: color }, isCurrent && styles.timelineCurrent]}>
                <Ionicons name={isDone ? 'checkmark' : isCurrent ? 'time' : 'ellipse-outline'} size={16} color={palette.white} />
              </View>
              {index < steps.length - 1 && <View style={[styles.timelineLine, { backgroundColor: isDone ? palette.green : palette.border }]} />}
            </View>
            <View style={[styles.timelineCard, isCurrent && styles.timelineCardCurrent]}>
              <View style={[styles.timelineTitles, { alignItems: contentAlignment(isRtl) }]}>
                <Text style={[styles.timelineAr, { textAlign: align }]}>{step.label.ar}</Text>
                <Text style={[styles.timelineEn, { textAlign: align }]}>{step.label.en}</Text>
                <Text style={[styles.timelineState, { textAlign: align }]}>{label(step.state_label)}</Text>
                {step.location && <Text style={[styles.timelineMeta, { textAlign: align }]}>{step.location}</Text>}
                {step.source && <Text style={[styles.timelineMeta, { textAlign: align }]}>{label(step.source)}</Text>}
                {step.public_note && <Text style={[styles.timelineNote, { textAlign: align }]}>{step.public_note}</Text>}
                {step.public_reason && <Text style={[styles.timelineReason, { textAlign: align }]}>{step.public_reason}</Text>}
              </View>
              {step.occurred_at && <Text style={[styles.timelineDate, { textAlign: align }]}>{new Date(step.occurred_at).toLocaleString()}</Text>}
            </View>
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: palette.background },
  screen: { paddingHorizontal: 18, paddingBottom: 36, gap: 18 },
  brandHeader: { alignItems: 'center', paddingVertical: 8, gap: 11 },
  brandCopy: {},
  brandNameAr: { color: palette.navy, fontSize: 17, fontWeight: '900' },
  brandNameEn: { color: palette.muted, fontSize: 11, fontWeight: '700', letterSpacing: 0.6 },
  brandMark: { width: 48, height: 48, borderRadius: 15, backgroundColor: palette.white, borderWidth: 1, borderColor: '#F0CDD1', alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  brandMarkImage: { width: 43, height: 43 },
  hero: { position: 'relative', overflow: 'hidden', backgroundColor: palette.navy, borderRadius: radii.large, padding: 22, minHeight: 185, justifyContent: 'flex-end', ...shadows.card },
  heroBrandMark: { width: 50, height: 50, borderRadius: 16, backgroundColor: palette.white, borderWidth: 1, borderColor: '#F0CDD1', alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  heroBrandImage: { width: 45, height: 45 },
  heroOrb: { position: 'absolute', width: 170, height: 170, borderRadius: 90, borderWidth: 2, borderColor: '#FFFFFF16', right: -55, top: -45 },
  heroEyebrow: { color: palette.brandRedLight, fontSize: 11, fontWeight: '900', letterSpacing: 1.8, textTransform: 'uppercase', marginTop: 18 },
  heroTitle: { color: palette.white, fontSize: 29, fontWeight: '900', marginTop: 4 },
  heroSubtitle: { color: '#C9DDE6', fontSize: 13, fontWeight: '600', lineHeight: 21, marginTop: 7 },
  sectionHeader: { justifyContent: 'space-between', alignItems: 'center', marginTop: 2 },
  sectionAr: { color: palette.navy, fontSize: 19, fontWeight: '900' },
  sectionEn: { color: palette.muted, fontSize: 12, fontWeight: '700', marginTop: 2 },
  metric: { flex: 1, minWidth: 145, borderRadius: radii.medium, padding: 16, gap: 4, ...shadows.card },
  metricNavy: { backgroundColor: palette.navySoft },
  metricGreen: { backgroundColor: palette.green },
  metricRed: { backgroundColor: palette.brandRed },
  metricValue: { color: palette.white, fontSize: 28, fontWeight: '900', marginTop: 8 },
  metricAr: { color: palette.white, fontSize: 13, fontWeight: '900' },
  metricEn: { color: '#FFFFFFCC', fontSize: 11, fontWeight: '600' },
  metricInteractive: { cursor: 'pointer' },
  metricPressed: { opacity: 0.82, transform: [{ scale: 0.98 }] },
  pill: { alignSelf: 'flex-start', borderRadius: radii.pill, paddingHorizontal: 10, paddingVertical: 6 },
  pillText: { fontSize: 11, fontWeight: '900' },
  progressTrack: { width: '100%', height: 8, borderRadius: radii.pill, backgroundColor: '#E5EDF1', overflow: 'hidden' },
  progressFill: { height: '100%', borderRadius: radii.pill, backgroundColor: palette.brandRed },
  infoRow: { alignItems: 'center', gap: 12, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: '#EAF0F3' },
  infoIcon: { width: 38, height: 38, borderRadius: 11, backgroundColor: palette.surfaceAlt, alignItems: 'center', justifyContent: 'center' },
  infoCopy: { flex: 1 },
  infoLabel: { color: palette.muted, fontSize: 11, fontWeight: '700' },
  infoValue: { color: palette.text, fontSize: 14, fontWeight: '800', marginTop: 3 },
  actionButton: { minHeight: 48, borderRadius: 13, backgroundColor: palette.navy, flexDirection: 'row', gap: 8, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16 },
  actionSecondary: { backgroundColor: palette.surface, borderWidth: 1, borderColor: palette.navy },
  actionText: { color: palette.white, fontSize: 13, fontWeight: '900' },
  actionSecondaryText: { color: palette.navy },
  pressed: { opacity: 0.78, transform: [{ scale: 0.99 }] },
  timeline: { gap: 0 },
  timelineRow: { minHeight: 82, alignItems: 'stretch' },
  timelineRail: { width: 42, alignItems: 'center' },
  timelineDot: { width: 34, height: 34, borderRadius: 18, alignItems: 'center', justifyContent: 'center', zIndex: 2 },
  timelineCurrent: { borderWidth: 4, borderColor: palette.brandRedSoft },
  timelineLine: { position: 'absolute', top: 34, bottom: -2, width: 3 },
  timelineCard: { flex: 1, minHeight: 62, marginBottom: 12, borderRadius: 13, backgroundColor: palette.surface, padding: 13, gap: 7, borderWidth: 1, borderColor: palette.border },
  timelineCardCurrent: { backgroundColor: palette.brandRedSoft, borderColor: palette.brandRed },
  timelineTitles: { flex: 1 },
  timelineAr: { color: palette.navy, fontSize: 14, fontWeight: '900' },
  timelineEn: { color: palette.muted, fontSize: 11, fontWeight: '700', marginTop: 2 },
  timelineState: { color: palette.blue, fontSize: 10, fontWeight: '900', marginTop: 5 },
  timelineMeta: { color: palette.muted, fontSize: 10, fontWeight: '700', marginTop: 3 },
  timelineNote: { color: palette.text, fontSize: 11, lineHeight: 17, marginTop: 5 },
  timelineReason: { color: '#8A5A00', fontSize: 10, lineHeight: 16, marginTop: 3 },
  timelineDate: { color: palette.blue, fontSize: 10, fontWeight: '800' },
  offlineBanner: { minHeight: 46, borderRadius: 12, backgroundColor: palette.orangeSoft, borderWidth: 1, borderColor: palette.orange, paddingHorizontal: 12, alignItems: 'center', justifyContent: 'center', gap: 8 },
  offlineText: { color: '#795000', fontSize: 10, fontWeight: '900', textAlign: 'center' },
});
