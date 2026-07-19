import { Ionicons } from '@expo/vector-icons';
import { useNetInfo } from '@react-native-community/netinfo';
import { PropsWithChildren, ReactNode } from 'react';
import {
  Pressable,
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
import { palette, radii, shadows } from '@/src/theme';

type IconName = keyof typeof Ionicons.glyphMap;

export function AppScreen({ children, contentStyle, refreshing = false, onRefresh }: PropsWithChildren<{ contentStyle?: StyleProp<ViewStyle>; refreshing?: boolean; onRefresh?: () => void }>) {
  const network = useNetInfo();
  const offline = network.isConnected === false || network.isInternetReachable === false;
  return (
    <SafeAreaView edges={['top']} style={styles.safeArea}>
      <ScrollView
        contentContainerStyle={[styles.screen, contentStyle]}
        refreshControl={onRefresh ? <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={palette.gold} /> : undefined}
        showsVerticalScrollIndicator={false}>
        {offline && <View style={styles.offlineBanner}><Ionicons name="cloud-offline-outline" size={18} color="#795000" /><Text style={styles.offlineText}>وضع عدم الاتصال · عرض آخر بيانات محفوظة / Offline · last known data</Text></View>}
        {children}
      </ScrollView>
    </SafeAreaView>
  );
}

export function BrandHeader({ titleAr, titleEn }: { titleAr?: string; titleEn?: string }) {
  return (
    <View style={styles.brandHeader}>
      <View style={styles.brandCopy}>
        <Text style={styles.brandNameAr}>{titleAr ?? 'مركز العميل'}</Text>
        <Text style={styles.brandNameEn}>{titleEn ?? 'Customer Center'}</Text>
      </View>
      <View style={styles.brandMark}>
        <Text style={styles.brandMarkText}>V</Text>
      </View>
    </View>
  );
}

export function Hero({ eyebrow, title, subtitle, icon = 'sparkles' }: { eyebrow: string; title: string; subtitle: string; icon?: IconName }) {
  const { isRtl } = useI18n();
  const textAlign = isRtl ? 'right' : 'left';
  return (
    <View style={styles.hero}>
      <View style={styles.heroOrb} />
      <Ionicons name={icon} size={34} color={palette.gold} />
      <Text style={[styles.heroEyebrow, { textAlign }]}>{eyebrow}</Text>
      <Text style={[styles.heroTitle, { textAlign }]}>{title}</Text>
      <Text style={[styles.heroSubtitle, { textAlign }]}>{subtitle}</Text>
    </View>
  );
}

export function SectionHeader({ ar, en, action }: { ar: string; en: string; action?: ReactNode }) {
  return (
    <View style={styles.sectionHeader}>
      <View>
        <Text style={styles.sectionAr}>{ar}</Text>
        <Text style={styles.sectionEn}>{en}</Text>
      </View>
      {action}
    </View>
  );
}

export function MetricCard({ icon, value, ar, en, tone = 'navy' }: { icon: IconName; value: string; ar: string; en: string; tone?: 'navy' | 'green' | 'gold' }) {
  const toneStyle = tone === 'green' ? styles.metricGreen : tone === 'gold' ? styles.metricGold : styles.metricNavy;
  return (
    <View style={[styles.metric, toneStyle]}>
      <Ionicons name={icon} size={22} color={tone === 'gold' ? palette.navy : palette.white} />
      <Text style={[styles.metricValue, tone === 'gold' && styles.metricDark]}>{value}</Text>
      <Text style={[styles.metricAr, tone === 'gold' && styles.metricDark]}>{ar}</Text>
      <Text style={[styles.metricEn, tone === 'gold' && styles.metricDark]}>{en}</Text>
    </View>
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
  return (
    <View style={styles.infoRow}>
      <View style={styles.infoIcon}><Ionicons name={icon} size={19} color={palette.blue} /></View>
      <View style={styles.infoCopy}>
        <Text style={styles.infoLabel}>{ar} / {en}</Text>
        <Text style={styles.infoValue}>{value}</Text>
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
  return (
    <View style={styles.timeline}>
      {steps.map((step, index) => {
        const isDone = step.state === 'completed';
        const isCurrent = step.state === 'current';
        const color = isDone ? palette.green : isCurrent ? palette.gold : '#B8C7CE';
        return (
          <View key={`${step.code}-${index}`} style={styles.timelineRow}>
            <View style={styles.timelineRail}>
              <View style={[styles.timelineDot, { backgroundColor: color }, isCurrent && styles.timelineCurrent]}>
                <Ionicons name={isDone ? 'checkmark' : isCurrent ? 'time' : 'ellipse-outline'} size={16} color={isCurrent ? palette.navy : palette.white} />
              </View>
              {index < steps.length - 1 && <View style={[styles.timelineLine, { backgroundColor: isDone ? palette.green : palette.border }]} />}
            </View>
            <View style={[styles.timelineCard, isCurrent && styles.timelineCardCurrent]}>
              <View style={styles.timelineTitles}>
                <Text style={styles.timelineAr}>{step.label.ar}</Text>
                <Text style={styles.timelineEn}>{step.label.en}</Text>
              </View>
              {step.occurred_at && <Text style={styles.timelineDate}>{new Date(step.occurred_at).toLocaleDateString()}</Text>}
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
  brandHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', paddingVertical: 8, gap: 11 },
  brandCopy: { alignItems: 'flex-end' },
  brandNameAr: { color: palette.navy, fontSize: 17, fontWeight: '900', textAlign: 'right' },
  brandNameEn: { color: palette.muted, fontSize: 11, fontWeight: '700', letterSpacing: 0.6 },
  brandMark: { width: 44, height: 44, borderRadius: 13, backgroundColor: palette.cyan, alignItems: 'center', justifyContent: 'center' },
  brandMarkText: { color: palette.white, fontWeight: '900', fontSize: 23 },
  hero: { position: 'relative', overflow: 'hidden', backgroundColor: palette.navy, borderRadius: radii.large, padding: 22, minHeight: 185, justifyContent: 'flex-end', ...shadows.card },
  heroOrb: { position: 'absolute', width: 170, height: 170, borderRadius: 90, borderWidth: 2, borderColor: '#FFFFFF16', right: -55, top: -45 },
  heroEyebrow: { color: palette.gold, fontSize: 11, fontWeight: '900', letterSpacing: 1.8, textTransform: 'uppercase', marginTop: 18, textAlign: 'right' },
  heroTitle: { color: palette.white, fontSize: 29, fontWeight: '900', textAlign: 'right', marginTop: 4 },
  heroSubtitle: { color: '#C9DDE6', fontSize: 13, fontWeight: '600', textAlign: 'right', lineHeight: 21, marginTop: 7 },
  sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 2 },
  sectionAr: { color: palette.navy, fontSize: 19, fontWeight: '900', textAlign: 'right' },
  sectionEn: { color: palette.muted, fontSize: 12, fontWeight: '700', marginTop: 2 },
  metric: { flex: 1, minWidth: 145, borderRadius: radii.medium, padding: 16, gap: 4, ...shadows.card },
  metricNavy: { backgroundColor: palette.navySoft },
  metricGreen: { backgroundColor: palette.green },
  metricGold: { backgroundColor: palette.gold },
  metricValue: { color: palette.white, fontSize: 28, fontWeight: '900', marginTop: 8 },
  metricAr: { color: palette.white, fontSize: 13, fontWeight: '900', textAlign: 'right' },
  metricEn: { color: '#FFFFFFCC', fontSize: 11, fontWeight: '600' },
  metricDark: { color: palette.navy },
  pill: { alignSelf: 'flex-start', borderRadius: radii.pill, paddingHorizontal: 10, paddingVertical: 6 },
  pillText: { fontSize: 11, fontWeight: '900' },
  progressTrack: { width: '100%', height: 8, borderRadius: radii.pill, backgroundColor: '#E5EDF1', overflow: 'hidden' },
  progressFill: { height: '100%', borderRadius: radii.pill, backgroundColor: palette.gold },
  infoRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: '#EAF0F3' },
  infoIcon: { width: 38, height: 38, borderRadius: 11, backgroundColor: palette.surfaceAlt, alignItems: 'center', justifyContent: 'center' },
  infoCopy: { flex: 1, alignItems: 'flex-end' },
  infoLabel: { color: palette.muted, fontSize: 11, fontWeight: '700', textAlign: 'right' },
  infoValue: { color: palette.text, fontSize: 14, fontWeight: '800', textAlign: 'right', marginTop: 3 },
  actionButton: { minHeight: 48, borderRadius: 13, backgroundColor: palette.navy, flexDirection: 'row', gap: 8, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 16 },
  actionSecondary: { backgroundColor: palette.surface, borderWidth: 1, borderColor: palette.navy },
  actionText: { color: palette.white, fontSize: 13, fontWeight: '900' },
  actionSecondaryText: { color: palette.navy },
  pressed: { opacity: 0.78, transform: [{ scale: 0.99 }] },
  timeline: { gap: 0 },
  timelineRow: { minHeight: 82, flexDirection: 'row', alignItems: 'stretch' },
  timelineRail: { width: 42, alignItems: 'center' },
  timelineDot: { width: 34, height: 34, borderRadius: 18, alignItems: 'center', justifyContent: 'center', zIndex: 2 },
  timelineCurrent: { borderWidth: 4, borderColor: palette.goldSoft },
  timelineLine: { position: 'absolute', top: 34, bottom: -2, width: 3 },
  timelineCard: { flex: 1, minHeight: 62, marginBottom: 12, borderRadius: 13, backgroundColor: palette.surface, padding: 13, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', borderWidth: 1, borderColor: palette.border },
  timelineCardCurrent: { backgroundColor: '#FFF9E9', borderColor: palette.gold },
  timelineTitles: { flex: 1, alignItems: 'flex-end' },
  timelineAr: { color: palette.navy, fontSize: 14, fontWeight: '900', textAlign: 'right' },
  timelineEn: { color: palette.muted, fontSize: 11, fontWeight: '700', marginTop: 2 },
  timelineDate: { color: palette.blue, fontSize: 11, fontWeight: '800', marginRight: 8 },
  offlineBanner: { minHeight: 46, borderRadius: 12, backgroundColor: palette.orangeSoft, borderWidth: 1, borderColor: palette.gold, paddingHorizontal: 12, flexDirection: 'row-reverse', alignItems: 'center', justifyContent: 'center', gap: 8 },
  offlineText: { color: '#795000', fontSize: 10, fontWeight: '900', textAlign: 'center' },
});
