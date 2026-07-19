import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text } from 'react-native';

import { DownloadTarget, downloadAndShare } from '@/src/files/authenticatedDownload';
import { useI18n } from '@/src/i18n/I18nProvider';
import { palette } from '@/src/theme';

export function DownloadButton({ target, filename }: { target: DownloadTarget; filename: string }) {
  const { language, isRtl } = useI18n();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  async function download() {
    setBusy(true); setError('');
    try { await downloadAndShare(target, filename); }
    catch { setError(language === 'ar' ? 'تعذر تنزيل الملف.' : 'The file could not be downloaded.'); }
    finally { setBusy(false); }
  }
  const accessibilityLabel = language === 'ar' ? `تنزيل آمن: ${filename}` : `Secure download: ${filename}`;
  return <>{error ? <Text accessibilityRole="alert" style={[styles.error, { textAlign: isRtl ? 'right' : 'left' }]}>{error}</Text> : null}<Pressable accessibilityLabel={accessibilityLabel} accessibilityRole="button" disabled={busy} onPress={download} style={styles.button}>{busy ? <ActivityIndicator color={palette.white} /> : <Text style={styles.text}>{language === 'ar' ? 'تنزيل آمن' : 'Secure download'}</Text>}</Pressable></>;
}

const styles = StyleSheet.create({ button: { minHeight: 44, borderRadius: 12, backgroundColor: palette.navy, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 14 }, text: { color: palette.white, fontSize: 11, fontWeight: '900' }, error: { color: palette.red, fontSize: 10 } });
