import { describe, expect, it } from 'vitest';

import { commercialDocumentTypeLabel, reportTypeLabel, requestedFromLabel, transactionDirectionLabel } from '@/src/i18n/domainLabels';
import { contentAlignment, leadingRowDirection, textAlignment } from '@/src/i18n/layout';

describe('bilingual domain labels and direction helpers', () => {
  it('translates server codes instead of showing raw codes', () => {
    expect(transactionDirectionLabel('debit', 'ar')).toBe('مدين');
    expect(commercialDocumentTypeLabel('packing_list', 'en')).toBe('Packing list');
    expect(reportTypeLabel('quality_control', 'ar')).toBe('فحص جودة');
    expect(requestedFromLabel('carrier', 'en')).toBe('Carrier');
  });

  it('keeps Arabic RTL and English LTR alignments explicit', () => {
    expect(textAlignment(true)).toBe('right');
    expect(contentAlignment(true)).toBe('flex-end');
    expect(leadingRowDirection(true)).toBe('row');
    expect(textAlignment(false)).toBe('left');
    expect(contentAlignment(false)).toBe('flex-start');
    expect(leadingRowDirection(false)).toBe('row');
  });
});
