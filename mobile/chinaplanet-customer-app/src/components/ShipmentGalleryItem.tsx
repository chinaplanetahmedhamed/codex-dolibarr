import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { Document } from '@/src/api/types';
import { DownloadButton } from '@/src/components/DownloadButton';
import { DetailRow, formatDate } from '@/src/components/DataCard';
import { loadAuthenticatedPreview } from '@/src/files/authenticatedPreview';
import { useI18n } from '@/src/i18n/I18nProvider';
import { palette, radii, shadows } from '@/src/theme';

export function ShipmentGalleryItem({ item }: { item: Document }) {
  const { language, isRtl } = useI18n();
  const [preview, setPreview] = useState('');
  const [previewError, setPreviewError] = useState(false);
  const align = isRtl ? 'right' : 'left';

  useEffect(() => {
    if (item.kind !== 'image') return;
    let active = true;
    let revoke: () => void = () => undefined;
    setPreview(''); setPreviewError(false);
    void loadAuthenticatedPreview(
      { kind: 'document', id: item.id },
      `${item.id}-${item.updated_at}`,
      item.name,
    ).then((loaded) => {
      revoke = loaded.revoke;
      if (active) setPreview(loaded.uri); else loaded.revoke();
    }).catch(() => { if (active) setPreviewError(true); });
    return () => { active = false; revoke(); };
  }, [item.id, item.kind, item.name, item.updated_at]);

  return <View style={styles.card}>
    {item.kind === 'image' ? <View style={styles.preview}>
      {preview ? <Image accessibilityLabel={item.name} contentFit="cover" source={{ uri: preview }} style={styles.image} /> : previewError ? <Ionicons name="image-outline" size={42} color={palette.muted} /> : <ActivityIndicator color={palette.brandRed} />}
    </View> : <View style={styles.fileIcon}><Ionicons name={item.kind === 'video' ? 'videocam' : 'document-text'} size={34} color={palette.brandRed} /></View>}
    <Text numberOfLines={3} style={[styles.name, { textAlign: align }]}>{item.name}</Text>
    {item.shipment_ref ? <DetailRow label={language === 'ar' ? 'الشحنة' : 'Shipment'} value={item.shipment_ref} /> : null}
    <DetailRow label={language === 'ar' ? 'الحجم' : 'Size'} value={formatBytes(item.size_bytes)} />
    <DetailRow label={language === 'ar' ? 'تاريخ النشر' : 'Published'} value={formatDate(item.approved_at, language)} />
    <DownloadButton target={{ kind: 'document', id: item.id }} filename={item.name} />
  </View>;
}

export function formatBytes(bytes: number) {
  const safe = Math.max(0, Number.isFinite(bytes) ? bytes : 0);
  if (safe < 1024) return `${safe} B`;
  if (safe < 1024 * 1024) return `${Math.ceil(safe / 1024)} KB`;
  return `${(safe / (1024 * 1024)).toFixed(1)} MB`;
}

const styles = StyleSheet.create({
  card: { backgroundColor: palette.surface, borderRadius: radii.medium, borderWidth: 1, borderColor: palette.border, padding: 14, gap: 11, ...shadows.card },
  preview: { height: 190, borderRadius: 14, overflow: 'hidden', backgroundColor: palette.surfaceAlt, alignItems: 'center', justifyContent: 'center' },
  image: { width: '100%', height: '100%' },
  fileIcon: { height: 92, borderRadius: 14, backgroundColor: palette.brandRedSoft, alignItems: 'center', justifyContent: 'center' },
  name: { color: palette.navy, fontSize: 14, lineHeight: 21, fontWeight: '900' },
});
