import { Image } from 'expo-image';
import { useVideoPlayer, VideoView } from 'expo-video';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Platform, Pressable, StyleSheet, Text, View } from 'react-native';

import { QcEvidence } from '@/src/api/types';
import { DownloadButton } from '@/src/components/DownloadButton';
import { mobileApiClient } from '@/src/api/client';
import { environment } from '@/src/config/environment';
import { loadAuthenticatedPreview } from '@/src/files/authenticatedPreview';
import { fetchAuthenticatedRange, targetPath } from '@/src/files/authenticatedDownload';
import { boundedRange, codecMime, parseSidx, segmentForTime } from '@/src/files/fragmentedMp4';
import { createVideoAuthRetryGuard } from '@/src/files/videoAuthRetry';
import { useI18n } from '@/src/i18n/I18nProvider';
import { palette, radii } from '@/src/theme';

export function QcEvidenceItem({ reportId, evidence }: { reportId: string; evidence: QcEvidence }) {
  const { language, isRtl } = useI18n();
  const [uri, setUri] = useState('');
  const [failed, setFailed] = useState(false);
  const [streaming, setStreaming] = useState(false);
  const player = useVideoPlayer(null);
  const webStreamCleanup = useRef<() => void>(() => undefined);
  const target = useMemo(() => ({ kind: 'qc-evidence' as const, reportId, evidenceId: evidence.id }), [evidence.id, reportId]);

  useEffect(() => {
    let active = true; let revoke: () => void = () => undefined;
    setUri(''); setFailed(false);
    if (evidence.kind === 'image') {
      void loadAuthenticatedPreview(target, `${reportId}-${evidence.id}-${evidence.sha256}`, 'evidence.jpg')
        .then((preview) => { revoke = preview.revoke; if (active) setUri(preview.uri); else preview.revoke(); })
        .catch(() => { if (active) setFailed(true); });
    } else if (Platform.OS !== 'web') {
      let refreshing = false; const authRetry = createVideoAuthRetryGuard();
      const install = async (position = 0, resume = false) => {
        const token = await mobileApiClient.getAccessToken();
        await player.replaceAsync({ uri: `${environment.apiBaseUrl}${targetPath(target)}`, headers: { Authorization: `Bearer ${token}` }, contentType: 'progressive', useCaching: false });
        if (position > 0) player.currentTime = position;
        if (resume) player.play();
      };
      void install().catch(() => { if (active) setFailed(true); });
      const subscription = player.addListener('statusChange', ({ status }) => {
        if (!active || refreshing) return;
        if (status === 'readyToPlay') { authRetry.reset(); setFailed(false); return; }
        if (status !== 'error') return;
        const delay = authRetry.nextDelay(); if (delay === null) { setFailed(true); return; }
        refreshing = true; const position = player.currentTime; const resume = player.playing;
        setTimeout(() => { void mobileApiClient.request('/me').then(() => install(position, resume)).catch(() => { if (active) setFailed(true); }).finally(() => { refreshing = false; }); }, delay);
      });
      return () => { active = false; subscription.remove(); revoke(); player.pause(); };
    }
    return () => { active = false; webStreamCleanup.current(); revoke(); player.pause(); };
  }, [evidence.id, evidence.kind, evidence.sha256, player, reportId, target]);

  const startWebVideo = async () => {
    setFailed(false); setStreaming(true); webStreamCleanup.current();
    try { webStreamCleanup.current = await createAuthenticatedWebStream(target, evidence.size_bytes, player); }
    catch { setFailed(true); setStreaming(false); }
  };

  const label = evidence.caption || (language === 'ar' ? (evidence.kind === 'video' ? 'فيديو فحص معتمد' : 'صورة فحص معتمدة') : `Approved inspection ${evidence.kind}`);
  return <View style={styles.card}>
    <View style={styles.preview}>
      {evidence.kind === 'image' ? (uri ? <Image accessibilityLabel={label} contentFit="contain" source={{ uri }} style={styles.media} /> : failed ? <Text>{language === 'ar' ? 'تعذر عرض الصورة' : 'Image unavailable'}</Text> : <ActivityIndicator color={palette.brandRed} />) : (Platform.OS === 'web' && !streaming ? <Pressable accessibilityRole="button" onPress={() => void startWebVideo()} style={styles.play}><Text style={styles.playText}>{failed ? (language === 'ar' ? 'إعادة محاولة بث الفيديو' : 'Retry secure video stream') : (language === 'ar' ? 'تشغيل الفيديو الآمن' : 'Play secure video')}</Text></Pressable> : failed ? <Text>{language === 'ar' ? 'تعذر بث الفيديو' : 'Video stream unavailable'}</Text> : <VideoView accessibilityLabel={label} contentFit="contain" nativeControls player={player} playsInline style={styles.media} />)}
    </View>
    <Text style={[styles.caption, { textAlign: isRtl ? 'right' : 'left' }]}>{label}</Text>
    <DownloadButton target={target} filename={evidence.kind === 'video' ? 'QC-video.mp4' : 'QC-image.jpg'} />
  </View>;
}

async function createAuthenticatedWebStream(target: { kind: 'qc-evidence'; reportId: string; evidenceId: string }, immutableSize: number, player: ReturnType<typeof useVideoPlayer>) {
  if (typeof MediaSource === 'undefined' || immutableSize <= 0) throw new Error('media_source_unavailable');
  const controller = new AbortController(); const mediaSource = new MediaSource(); const objectUrl = URL.createObjectURL(mediaSource); let closed = false;
  const opened = new Promise<void>((resolve, reject) => { mediaSource.addEventListener('sourceopen', () => resolve(), { once: true }); mediaSource.addEventListener('sourceclose', () => reject(new Error('stream_closed')), { once: true }); });
  await player.replaceAsync(objectUrl);
  const pump = async () => {
    await opened;
    let headSize = Math.min(immutableSize, 1024 * 1024), range = boundedRange(0, headSize, immutableSize), head = await fetchRangeBytes(target, range.start, range.end, immutableSize, controller.signal), index = parseSidx(head);
    while (!index && headSize < Math.min(immutableSize, 8 * 1024 * 1024)) { headSize = Math.min(immutableSize, headSize * 2); range = boundedRange(0, headSize, immutableSize); head = await fetchRangeBytes(target, range.start, range.end, immutableSize, controller.signal); index = parseSidx(head); }
    if (!index || index.entries.length === 0 || index.mediaOffset <= 0 || index.mediaOffset > immutableSize) throw new Error('video_seek_index_missing');
    const init = index.mediaOffset <= head.byteLength ? head.slice(0, index.mediaOffset) : await fetchRangeBytes(target, 0, index.mediaOffset - 1, immutableSize, controller.signal), mime = codecMime(init);
    if (!MediaSource.isTypeSupported(mime)) throw new Error('video_codec_unsupported'); const source = mediaSource.addSourceBuffer(mime); await appendBuffer(source, init);
    let next = 0; let requested: number | null = null; const loaded = new Set<number>();
    const watcher = setInterval(() => { if (closed) return; const time = Math.max(0, player.currentTime); if (!isBuffered(source, time)) requested = segmentForTime(index!, time); }, 250);
    try { while (!closed) { const seekRequested=requested!==null;if(!seekRequested&&next>=index.entries.length){await pause(250);continue;}const selected = seekRequested ? requested! : next; requested = null; if (loaded.has(selected)&&!seekRequested) { next += 1; await pause(50); continue; } const entry = index.entries[selected], segmentRange = boundedRange(entry.offset, entry.size, immutableSize); if (segmentRange.end !== entry.offset + entry.size - 1) throw new Error('truncated_video_segment'); const bytes = await fetchRangeBytes(target, segmentRange.start, segmentRange.end, immutableSize, controller.signal); await appendBuffer(source, bytes); loaded.add(selected); while (next < index.entries.length && loaded.has(next)) next += 1; if (player.currentTime > 90 && !source.updating && source.buffered.length && source.buffered.start(0) < player.currentTime - 60) { const cutoff=player.currentTime-60;source.remove(source.buffered.start(0),cutoff);await updateEnd(source);index.entries.forEach((candidate,entryIndex)=>{if(candidate.start+candidate.duration<=cutoff)loaded.delete(entryIndex);}); } while (!closed && bufferedAhead(source, player.currentTime) > 60 && requested === null) await pause(500); } } finally { clearInterval(watcher); }
  };
  void pump().catch(() => { if (!closed && mediaSource.readyState === 'open') mediaSource.endOfStream('network'); });
  return () => { closed = true; controller.abort(); player.pause(); URL.revokeObjectURL(objectUrl); };
}

async function fetchRangeBytes(target:{kind:'qc-evidence';reportId:string;evidenceId:string},start:number,end:number,total:number,signal:AbortSignal){if(start<0||end<start||end>=total)throw new Error('invalid_video_range');const response=await fetchAuthenticatedRange(target,start,end,mobileApiClient,fetch,signal);if(response.status!==206)throw new Error(`stream_failed_${response.status}`);const header=response.headers.get('Content-Range')||'',match=/bytes\s+(\d+)-(\d+)\/(\d+)/i.exec(header);if(!match||Number(match[1])!==start||Number(match[2])!==end||Number(match[3])!==total)throw new Error('invalid_content_range');const bytes=await response.arrayBuffer();if(bytes.byteLength!==end-start+1)throw new Error('invalid_video_range_length');return bytes;}
function appendBuffer(source:SourceBuffer,bytes:ArrayBuffer){source.appendBuffer(bytes);return updateEnd(source);}function updateEnd(source:SourceBuffer){return new Promise<void>((resolve,reject)=>{const done=()=>{source.removeEventListener('updateend',done);source.removeEventListener('error',fail);resolve();},fail=()=>{source.removeEventListener('updateend',done);source.removeEventListener('error',fail);reject(new Error('source_buffer_error'));};source.addEventListener('updateend',done);source.addEventListener('error',fail);});}
function isBuffered(source:SourceBuffer,time:number){for(let i=0;i<source.buffered.length;i++)if(time>=source.buffered.start(i)&&time<=source.buffered.end(i))return true;return false;}function bufferedAhead(source:SourceBuffer,time:number){for(let i=0;i<source.buffered.length;i++)if(time>=source.buffered.start(i)&&time<=source.buffered.end(i))return source.buffered.end(i)-time;return 0;}function pause(ms:number){return new Promise(resolve=>setTimeout(resolve,ms));}

const styles = StyleSheet.create({ card: { gap: 10 }, preview: { height: 230, borderRadius: radii.medium, overflow: 'hidden', backgroundColor: palette.navyDeep, alignItems: 'center', justifyContent: 'center' }, media: { width: '100%', height: '100%' }, play: { paddingHorizontal: 18, paddingVertical: 12, borderRadius: 10, backgroundColor: palette.brandRed }, playText: { color: palette.white, fontWeight: '900' }, caption: { color: palette.text, fontSize: 12, fontWeight: '700' } });
