export type SegmentIndex = { mediaOffset: number; entries: { offset: number; size: number; start: number; duration: number }[] };

export function boundedRange(start: number, desiredLength: number, total: number) {
  if (!Number.isInteger(start) || !Number.isInteger(desiredLength) || !Number.isInteger(total) || start < 0 || desiredLength <= 0 || start >= total) throw new Error('invalid_video_range');
  return { start, end: Math.min(total - 1, start + desiredLength - 1) };
}

export function parseSidx(bytes: ArrayBuffer): SegmentIndex | null {
  const view = new DataView(bytes); let at = 0;
  while (at + 8 <= view.byteLength) {
    let size = view.getUint32(at); const type = String.fromCharCode(view.getUint8(at + 4), view.getUint8(at + 5), view.getUint8(at + 6), view.getUint8(at + 7));
    if (size === 0) size = view.byteLength - at;
    if (size < 8 || at + size > view.byteLength) return null;
    if (type === 'sidx') {
      let p = at + 8; const version = view.getUint8(p); p += 4; p += 4; const timescale = view.getUint32(p); p += 4; if (!timescale) return null;
      let earliest: number; let firstOffset: number;
      if (version === 0) { earliest = view.getUint32(p); firstOffset = view.getUint32(p + 4); p += 8; }
      else { earliest = view.getUint32(p) * 4294967296 + view.getUint32(p + 4); firstOffset = view.getUint32(p + 8) * 4294967296 + view.getUint32(p + 12); p += 16; }
      p += 2; const count = view.getUint16(p); p += 2; let offset = at + size + firstOffset; let time = earliest / timescale; const entries = [];
      for (let i = 0; i < count; i += 1) { if (p + 12 > at + size) return null; const reference = view.getUint32(p); const duration = view.getUint32(p + 4) / timescale; if ((reference >>> 31) !== 0) return null; const segmentSize = reference & 0x7fffffff; entries.push({ offset, size: segmentSize, start: time, duration }); offset += segmentSize; time += duration; p += 12; }
      return { mediaOffset: entries[0]?.offset ?? 0, entries };
    }
    at += size;
  }
  return null;
}

export function codecMime(init: ArrayBuffer) {
  const bytes = new Uint8Array(init);
  for (let i = 4; i + 7 < bytes.length; i += 1) if (bytes[i] === 0x61 && bytes[i + 1] === 0x76 && bytes[i + 2] === 0x63 && bytes[i + 3] === 0x43) { const hex = (value: number) => value.toString(16).padStart(2, '0').toUpperCase(); return `video/mp4; codecs="avc1.${hex(bytes[i + 5])}${hex(bytes[i + 6])}${hex(bytes[i + 7])}, mp4a.40.2"`; }
  return 'video/mp4; codecs="avc1.42E01E, mp4a.40.2"';
}

export function segmentForTime(index: SegmentIndex, time: number) { const at = index.entries.findIndex((entry) => time >= entry.start && time < entry.start + entry.duration); return at < 0 ? Math.max(0, index.entries.length - 1) : at; }
