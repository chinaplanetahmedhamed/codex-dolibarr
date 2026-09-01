import { describe, expect, it } from 'vitest';

import { boundedRange, codecMime, parseSidx, segmentForTime } from '@/src/files/fragmentedMp4';

describe('fragmented MP4 secure range index', () => {
  it('bounds first and final requests for small and non-multiple files', () => {
    expect(boundedRange(0, 4 * 1024 * 1024, 1000)).toEqual({ start: 0, end: 999 });
    expect(boundedRange(4 * 1024 * 1024, 4 * 1024 * 1024, 5 * 1024 * 1024 + 7)).toEqual({ start: 4 * 1024 * 1024, end: 5 * 1024 * 1024 + 6 });
  });

  it('parses a version-zero SIDX and maps a seek time to its media range', () => {
    const bytes = new ArrayBuffer(56); const view = new DataView(bytes); view.setUint32(0, 56); ['s','i','d','x'].forEach((c, i) => view.setUint8(4 + i, c.charCodeAt(0))); view.setUint32(12, 1); view.setUint32(16, 1000); view.setUint32(20, 0); view.setUint32(24, 0); view.setUint16(30, 2); view.setUint32(32, 100); view.setUint32(36, 5000); view.setUint32(44, 120); view.setUint32(48, 5000);
    const index = parseSidx(bytes); expect(index?.entries).toEqual([{ offset: 56, size: 100, start: 0, duration: 5 }, { offset: 156, size: 120, start: 5, duration: 5 }]); expect(segmentForTime(index!, 7)).toBe(1);
  });

  it('derives the declared AVC profile from the immutable init segment', () => {
    const init = new Uint8Array([0,0,0,0,0x61,0x76,0x63,0x43,1,0x64,0,0x28]).buffer;
    expect(codecMime(init)).toContain('avc1.640028');
  });
});
