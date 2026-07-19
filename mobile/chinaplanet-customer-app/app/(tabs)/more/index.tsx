import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { AppScreen, BrandHeader, Hero, SectionHeader } from '@/src/components';
import { useAuth } from '@/src/auth/AuthProvider';
import { useI18n } from '@/src/i18n/I18nProvider';
import { contentAlignment, leadingRowDirection, textAlignment } from '@/src/i18n/layout';
import { palette, radii, shadows } from '@/src/theme';

type IconName = keyof typeof Ionicons.glyphMap;

export default function MoreScreen() {
  const { user, logout } = useAuth();
  const { language, setLanguage } = useI18n();
  return <AppScreen><BrandHeader titleAr="المزيد" titleEn="More" /><Hero eyebrow="CUSTOMER PROFILE" title={user?.display_name || '—'} subtitle={user?.company_name || '—'} icon="person-circle" /><SectionHeader ar="الخدمات" en="Services" /><View style={styles.menu}><Item icon="receipt" ar="الطلبات" en="Orders" onPress={() => router.push('/more/orders')} /><Item icon="documents" ar="المستندات" en="Documents" onPress={() => router.push('/more/documents')} /><Item icon="document-text" ar="فواتير الخدمات" en="Service invoices" onPress={() => router.push('/more/service-invoices')} /><Item icon="reader" ar="المستندات التجارية" en="Commercial documents" onPress={() => router.push('/more/commercial-documents')} /><Item icon="clipboard" ar="تقارير الجودة والخدمات" en="QC & service reports" onPress={() => router.push('/more/reports')} /></View><SectionHeader ar="الإعدادات" en="Settings" /><View style={styles.menu}><Item icon="language" ar="اللغة" en={language === 'ar' ? 'العربية' : 'English'} onPress={() => void setLanguage(language === 'ar' ? 'en' : 'ar')} /></View><Pressable accessibilityRole="button" onPress={() => void logout()} style={styles.logout}><Text style={styles.logoutText}>{language === 'ar' ? 'تسجيل الخروج' : 'Logout'}</Text><Ionicons name="log-out-outline" size={20} color={palette.red} /></Pressable><Text style={styles.version}>chinaplanetCS · M017 local</Text></AppScreen>;
}

function Item({ icon, ar, en, onPress }: { icon: IconName; ar: string; en: string; onPress: () => void }) {
  const { isRtl } = useI18n();
  const align = textAlignment(isRtl);
  return <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => [styles.item, { flexDirection: leadingRowDirection(isRtl) }, pressed && { opacity: 0.7 }]}><View style={styles.icon}><Ionicons name={icon} size={20} color={palette.blue} /></View><View style={[styles.copy, { alignItems: contentAlignment(isRtl) }]}><Text style={[styles.ar, { textAlign: align }]}>{ar}</Text><Text style={[styles.en, { textAlign: align }]}>{en}</Text></View><Ionicons name={isRtl ? 'chevron-back' : 'chevron-forward'} size={18} color={palette.muted} /></Pressable>;
}

const styles = StyleSheet.create({ menu: { backgroundColor: palette.surface, borderRadius: radii.medium, borderWidth: 1, borderColor: palette.border, overflow: 'hidden', ...shadows.card }, item: { minHeight: 68, alignItems: 'center', gap: 10, paddingHorizontal: 14, borderBottomWidth: 1, borderBottomColor: '#EAF0F3' }, copy: { flex: 1 }, ar: { color: palette.navy, fontSize: 13, fontWeight: '900' }, en: { color: palette.muted, fontSize: 10, marginTop: 2 }, icon: { width: 40, height: 40, borderRadius: 12, backgroundColor: palette.surfaceAlt, alignItems: 'center', justifyContent: 'center' }, logout: { minHeight: 51, borderRadius: 14, borderWidth: 1, borderColor: '#F2C8C6', backgroundColor: palette.redSoft, flexDirection: 'row', gap: 8, alignItems: 'center', justifyContent: 'center' }, logoutText: { color: palette.red, fontSize: 12, fontWeight: '900' }, version: { color: palette.muted, fontSize: 9, textAlign: 'center' } });
