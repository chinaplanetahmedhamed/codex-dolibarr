import { Ionicons } from '@expo/vector-icons';
import { StatusBar } from 'expo-status-bar';
import { useState } from 'react';
import { ActivityIndicator, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { MobileApiError, NetworkUnavailableError, RequestTimeoutError } from '@/src/api/errors';
import { useAuth } from '@/src/auth/AuthProvider';
import { useI18n } from '@/src/i18n/I18nProvider';
import { contentAlignment, leadingRowDirection, textAlignment } from '@/src/i18n/layout';
import { palette, radii, shadows } from '@/src/theme';

export default function LoginScreen() {
  const { login } = useAuth();
  const { language, isRtl, t } = useI18n();
  const align = textAlignment(isRtl);
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function submit() {
    if (!username.trim() || !password) {
      setError(language === 'ar' ? 'أدخل اسم المستخدم وكلمة المرور.' : 'Enter your username and password.');
      return;
    }
    setBusy(true);
    setError('');
    try {
      await login(username, password);
    } catch (caught) {
      if (caught instanceof MobileApiError) setError(caught.localized(language));
      else if (caught instanceof NetworkUnavailableError) setError(t('offline'));
      else if (caught instanceof RequestTimeoutError) setError(t('timeout'));
      else setError(t('genericError'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar style="light" />
      <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          <View style={[styles.brand, { flexDirection: leadingRowDirection(isRtl) }]}><View style={styles.logo}><Text style={styles.logoText}>CP</Text></View><View><Text style={[styles.brandAr, { textAlign: align }]}>عملاء كوكب الصين</Text><Text style={[styles.brandEn, { textAlign: align }]}>CHINAPLANET CUSTOMER</Text></View></View>
          <View style={[styles.intro, { alignItems: contentAlignment(isRtl) }]}><Text style={[styles.title, { textAlign: align }]}>{language === 'ar' ? 'أعمالك في الصين، أمامك في كل وقت.' : 'Your China business, always within reach.'}</Text><Text style={[styles.subtitle, { textAlign: align }]}>{language === 'ar' ? 'مشاريعك وشحناتك وحسابك ومستنداتك المعتمدة في مكان آمن واحد.' : 'Your projects, shipments, account and approved documents in one secure place.'}</Text></View>
          <View style={styles.card}>
            <Text style={[styles.cardTitle, { textAlign: align }]}>{t('signIn')}</Text>
            <View style={[styles.inputWrap, { flexDirection: leadingRowDirection(isRtl) }]}><Ionicons name="person-outline" size={20} color={palette.muted} /><TextInput autoCapitalize="none" autoCorrect={false} value={username} onChangeText={setUsername} placeholder={t('username')} style={[styles.input, { textAlign: align }]} textContentType="username" accessibilityLabel={t('username')} /></View>
            <View style={[styles.inputWrap, { flexDirection: leadingRowDirection(isRtl) }]}><Ionicons name="lock-closed-outline" size={20} color={palette.muted} /><TextInput value={password} onChangeText={setPassword} placeholder={t('password')} secureTextEntry style={[styles.input, { textAlign: align }]} textContentType="password" accessibilityLabel={t('password')} /></View>
            {!!error && <Text accessibilityRole="alert" style={[styles.error, { textAlign: align }]}>{error}</Text>}
            <Pressable accessibilityRole="button" disabled={busy} onPress={submit} style={({ pressed }) => [styles.button, (busy || pressed) && styles.pressed]}>
              {busy ? <ActivityIndicator color={palette.white} /> : <><Text style={styles.buttonText}>{t('signIn')}</Text><Ionicons name="arrow-back" size={19} color={palette.white} /></>}
            </Pressable>
            <Text style={styles.note}>لا تُحفظ كلمة المرور على الجهاز / Password is never stored</Text>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 }, safeArea: { flex: 1, backgroundColor: palette.navy }, content: { flexGrow: 1, justifyContent: 'center', gap: 28, padding: 24 },
  brand: { alignItems: 'center', gap: 12 }, logo: { width: 52, height: 52, borderRadius: 15, backgroundColor: palette.cyan, alignItems: 'center', justifyContent: 'center' }, logoText: { color: palette.white, fontWeight: '900', fontSize: 18 },
  brandAr: { color: palette.white, fontSize: 18, fontWeight: '900' }, brandEn: { color: '#BAD4DF', fontSize: 10, fontWeight: '800', letterSpacing: 1.3 },
  intro: {}, title: { color: palette.white, fontSize: 30, lineHeight: 43, fontWeight: '900' }, subtitle: { color: '#BFD5DE', fontSize: 12, lineHeight: 20, marginTop: 8 },
  card: { backgroundColor: palette.surface, borderRadius: radii.large, padding: 20, gap: 12, ...shadows.card }, cardTitle: { color: palette.navy, fontSize: 23, fontWeight: '900', marginBottom: 4 },
  inputWrap: { minHeight: 52, borderWidth: 1, borderColor: palette.border, borderRadius: 13, alignItems: 'center', gap: 8, paddingHorizontal: 13, backgroundColor: '#F9FBFC' }, input: { flex: 1, color: palette.text, fontSize: 14 },
  error: { color: palette.red, fontSize: 12, lineHeight: 19, fontWeight: '700' }, button: { minHeight: 52, borderRadius: 14, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 9, backgroundColor: palette.navy, marginTop: 4 }, buttonText: { color: palette.white, fontSize: 14, fontWeight: '900' }, pressed: { opacity: 0.7 }, note: { color: palette.muted, fontSize: 9, textAlign: 'center' },
});
