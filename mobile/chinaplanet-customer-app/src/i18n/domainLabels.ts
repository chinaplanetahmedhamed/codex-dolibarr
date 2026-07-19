import type { Language } from '@/src/api/types';

type Labels = { ar: string; en: string };

function translated(code: string, language: Language, labels: Record<string, Labels>) {
  return (labels[code] ?? { ar: code, en: code })[language];
}

export function transactionDirectionLabel(code: string, language: Language) {
  return translated(code, language, {
    debit: { ar: 'مدين', en: 'Debit' },
    credit: { ar: 'دائن', en: 'Credit' },
  });
}

export function commercialDocumentTypeLabel(code: string, language: Language) {
  return translated(code, language, {
    commercial_invoice: { ar: 'فاتورة تجارية', en: 'Commercial invoice' },
    packing_list: { ar: 'قائمة تعبئة', en: 'Packing list' },
    bill_of_lading: { ar: 'بوليصة شحن', en: 'Bill of lading' },
    cargox: { ar: 'مستند CargoX', en: 'CargoX document' },
    certificate_origin: { ar: 'شهادة منشأ', en: 'Certificate of origin' },
    inspection: { ar: 'مستند فحص', en: 'Inspection document' },
    other: { ar: 'مستند آخر', en: 'Other document' },
  });
}

export function reportTypeLabel(code: string, language: Language) {
  return translated(code, language, {
    order: { ar: 'طلب', en: 'Order' },
    factory_audit: { ar: 'تدقيق مصنع', en: 'Factory audit' },
    quality_control: { ar: 'فحص جودة', en: 'Quality control' },
    shipping_inquiry: { ar: 'استعلام شحن', en: 'Shipping inquiry' },
    shipping_booking: { ar: 'حجز شحن', en: 'Shipping booking' },
    loading: { ar: 'تحميل', en: 'Loading' },
    customs: { ar: 'جمارك', en: 'Customs' },
    claim: { ar: 'مطالبة', en: 'Claim' },
    other: { ar: 'تقرير آخر', en: 'Other report' },
  });
}

export function requestedFromLabel(code: string, language: Language) {
  return translated(code, language, {
    customer: { ar: 'العميل', en: 'Customer' },
    china_planet: { ar: 'فريق كوكب الصين', en: 'China Planet team' },
    carrier: { ar: 'الناقل', en: 'Carrier' },
    customs: { ar: 'الجمارك', en: 'Customs' },
    other: { ar: 'جهة أخرى', en: 'Other party' },
  });
}
