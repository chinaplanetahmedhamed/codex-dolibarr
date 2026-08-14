# Admin, Customer Portal, and Mobile App Parity

## 2026-08-06 — M044 Palestine shipping-country normalization

- Source change: Shipping Prices importer and public/staff country label normalize Ashdod and Haifa from `IL` to `PS`.
- Admin ERP: affected in Shipping Prices filters, port mappings, and future workbook imports.
- Customer portal: existing customer-safe price data contract remains; the affected destination label becomes Palestine.
- Mobile app: `NOT_APPLICABLE`; it does not own the public Shipping Prices importer or destination selector.
- API/data dependency: public route options return `PS` with the scoped Palestine label.
- Design impact: terminology only; no navigation, status, document, finance, or mobile layout change.
- Verification: pending M044 staging and production release acceptance.

## 2026-08-07 — M045 public-request quotation workspace

- Source change: the staff-only website-request detail gains structured product rows, safe images and mapping, selling-price selection/manual price, separated notes, and one idempotently linked standard draft `Propal`.
- Admin ERP: `STAGING_AUTHENTICATED_UI_VERIFIED_WITH_CURRENCY_BLOCKER`; M045-C01 is deployed on canonical `backup-erp`, the allowlisted `WP-9688B` product and packing fields are synchronized, and request `WEB-20260807-182858-81C9DA` renders the real image and `2 × 100 = 200 pieces` equation. USD draft creation remains blocked because staging exposes only EUR and no approved USD rate.
- Customer Portal: `NOT_APPLICABLE`; no customer-portal page, permission, data projection, document, or action changed.
- Mobile App: `NOT_APPLICABLE`; the app does not own public inquiry intake or staff quotation preparation, and `/api/mobile/v1` did not change.
- API/data dependency: the public React secure gateway remains the intake boundary; Dolibarr owns mapping and quotation authority. No supplier price, purchase cost, margin, private note, or staff-only metadata is added to customer/mobile contracts.
- Design/terminology impact: staff-only responsive product table/card behavior and standard Dolibarr proposal handoff; no customer/mobile terminology change.
- Phone verification: authenticated `390×844` staging verification is `PASS`; the product image and every packing, mapping, price-source, per-piece-price, and notes value are visible with no page-wide horizontal overflow.
- Status: `ADMIN_STAGING_UI_VERIFIED`, `USD_DRAFT_BLOCKED`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`, `PRODUCTION_NOT_ACCESSED`.
- Follow-up: configure USD and an approved conversion rate under a separate authorization, then create a synthetic draft only; do not email or validate a real customer proposal.

## Purpose

This is the canonical synchronization log for the three ChinaPlanetOS product surfaces:

1. Admin ERP;
2. Customer Portal;
3. Customer Mobile App.

Read this file before changing a mobile screen. Update it whenever an admin or customer-portal change affects customer-visible data, wording, navigation, status, action, document, invoice, finance, project, shipment, or design behavior.

## Source-of-truth rules

- Dolibarr remains the operational and accounting source of truth.
- The customer portal and `/api/mobile/v1` define the approved customer-visible contract.
- The mobile app must mirror the approved portal/API data and meaning. It must not infer customer permissions from the admin interface.
- Admin-only actions and supplier identity, costs, margins, private notes/files, staff metadata, credentials, and bank-private data remain excluded from the portal and mobile app.
- A shared design language is required across all three surfaces: terminology, brand colors, typography hierarchy, spacing, cards, icons, status colors, loading/empty/error states, and Arabic-default RTL with English/LTR.
- Web and native interactions may differ where the platform requires it, but the same business concept must look and behave recognizably across surfaces.

## Required verification

For every relevant change:

- verify actual data and customer ownership, not only labels or mock data;
- verify the admin page and external customer session separately;
- verify affected web pages on desktop and at a 390px phone viewport;
- verify wrapping, overflow, touch targets, tables/cards, navigation, empty/loading/error states, RTL/LTR, and design consistency;
- record whether mobile work is `IMPLEMENTED`, `PENDING`, or `NOT_APPLICABLE`;
- do not expand an approved mission silently to implement a pending surface.

## Entry template

```md
### YYYY-MM-DD — Mission or change name

- Source change:
- Admin ERP:
- Customer Portal:
- Mobile App:
- API/data dependency:
- Design/terminology impact:
- Phone verification:
- Status: ADMIN_VERIFIED | PORTAL_VERIFIED | MOBILE_IMPLEMENTED | MOBILE_PENDING | NOT_APPLICABLE
- Follow-up:
```

## قائمة التوافق الحالية

### 2026-07-24 — M037-C03 أمان بيانات JSON القديمة في سجل طلبات الشحن

- تغيير المصدر: أصبحت قراءة JSON لدورة طلب الشحن تتحقق من صلاحية القيمة القديمة وتستخدم كائنًا فارغًا عند فسادها بدل إسقاط استعلام الإحصاءات كله.
- ERP الإداري: `BACKEND_CORRECTED_LOCAL`؛ نجح اختبار runtime المحلي مع سجل JSON قديم غير صالح ومع الأعداد الدقيقة `7 / 4 / 1 / 1 / 1` وعزل الكيان والعميل وrollback نظيف.
- بوابة العميل: `NOT_APPLICABLE`؛ لا تغيير في البيانات أو المصطلحات أو الصلاحيات أو العرض.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ لا تغيير في API أو العقد أو التصميم.
- اعتماد API والبيانات: لا API جديد؛ التصحيح محصور في تجميع الإدارة الداخلي.
- أثر التصميم والمصطلحات: لا يوجد تغيير مرئي.
- التحقق على الهاتف: `NOT_APPLICABLE`؛ لا صفحة ويب متأثرة بصريًا.
- الحالة: `ADMIN_BACKEND_VERIFIED_LOCAL`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`.
- المتابعة: بُني محليًا artifact مقفول بالـ checksum من 71 ملفًا باسم `m037-full-04bf778.tar.gz`؛ لم يُرفع أو يُطبق، ولا نشر دون موافقة مستقلة.

### 2026-07-23 — M036 سجل طلبات الشحن ولوحات الإدارة

- تغيير المصدر: أضيف سجل إداري موحد لطلبات حجز الشحن مع حالات `Total Current`, `Active`, `Confirmed / SO Received`, `Closed`, و`Cancelled`، وربط داخل مركز الشحن ومركز العميل المحدد ولوحة الإدارة الرئيسية.
- ERP الإداري: `RUNTIME_VERIFIED_LOCAL`؛ السجل يستمد الطلب التشغيلي من `cpo_operation` والدليل التجاري المقبول فقط من snapshot الحجز الثابت، ويفصل بوضوح بين طلب ChinaPlanet وCarrier SO وطلب البيع الاختياري والشحنة المرتبطة. اختلاف الملكية يظهر كاستثناء داخلي مع إخفاء دليل السعر.
- بوابة العميل: `UNCHANGED_VERIFIED`؛ لم يُضف سجل الإدارة أو العدادات أو بيانات التشغيل الداخلية إلى المستخدم الخارجي، وتحقق رفض فتح المسار الإداري بجلسة عميل فعلية. يبقى تاريخ الحجز الآمن الذي أقرته M035 كما هو.
- تطبيق الهاتف: `PENDING` وخارج نطاق M036. يحتاج عقد API للهاتف وشاشة أصلية لاحقة لعرض تاريخ طلبات الحجز الآمن وحالاته؛ لا يجوز نسخ سجل الإدارة أو ملاحظاته أو حقول الناقل الخاصة.
- اعتماد API والبيانات: لا API جديد في M036. التجميع الإداري يفرض الكيان الحالي، ومركز العميل المحدد يفرض `entity + fk_soc`، بينما أي تنفيذ هاتف لاحق يجب أن يشتق السلطة من جلسة العميل لا من معاملات يرسلها التطبيق.
- أثر التصميم والمصطلحات: المصطلح المعتمد `Shipping Orders / طلبات الشحن` منفصل عن `Sales Orders`. لا تُستخدم كلمة `Succeeded`؛ التأكيد يعني استلام Carrier SO/booking evidence، والإغلاق يعني اكتمال العملية.
- التحقق على الهاتف: سجل الطلبات ولوحة الإدارة ومركز العميل المحدد `PASS` عند 1440 و1024 و768 و390 و320 دون overflow مادي؛ بيانات محلية مؤقتة غطت الحالات الأربع وتحذيرات الملكية ثم حُذفت، ونجح سجل الطلبات عند تكبير Chrome الفعلي 200%. مركز الشحن أظهر الرابط المتجاوب، والعرض الخارجي بقي دون عناصر M036.
- الحالة: `ADMIN_RUNTIME_VERIFIED_LOCAL`, `PORTAL_UNCHANGED_VERIFIED`, `MOBILE_PENDING`.
- المتابعة: إنشاء Mission مستقلة لعقد API وشاشة الهاتف بعد اعتماد العرض الآمن للحالة؛ لا إرسال لتكاليف أو هامش أو forwarder أو ملاحظات خاصة أو raw snapshots.

### 2026-07-22 — M034 حالة نشر أسعار الشحن الداخلية

- تغيير المصدر: أصبحت واجهة استيراد أسعار الشحن تفرق بين المسودة والدفعة المنشورة الحالية والسجل المنشور، وتشرح إعادة استخدام الملف المطابق، وتعرض أعداد الأسعار الحالية والمتاحة في بحث العميل.
- ERP الإداري: `VERIFIED_LOCAL`؛ التغيير محصور بأدوات الموظفين الداخلية مع نشر وإعادة تفعيل عبر POST وCSRF وحماية من تفعيل سجل أقدم.
- بوابة العميل: `NOT_APPLICABLE`؛ لم يتغير بحث الأسعار أو عقد البيانات أو شاشة العميل، وإنما أصبحت الإدارة ترى عدد الأسعار التي ستظهر في البحث قبل اعتبار النشر ناجحًا.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ لا تغيير في API أو البيانات أو المصطلحات أو التصميم المرئي للعميل.
- اعتماد API والبيانات: يعتمد عداد البحث على الأسعار المنشورة الحالية العامة التي تتجاوز حد السعر المهيأ؛ لا يرسل التطبيق أو البوابة أي صلاحية إدارية.
- أثر التصميم والمصطلحات: توضيح حالة النشر داخل الإدارة فقط باستخدام حالات ثنائية اللغة وأعداد قابلة للتدقيق.
- التحقق على الهاتف: `PASS` لصفحتي الاستيراد ومراجعة الدفعة عند 390×844 دون overflow مادي أو تحذيرات PHP؛ لا صفحة عميل أو هاتف متأثرة.
- الحالة: `ADMIN_VERIFIED_LOCAL`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`.
- المتابعة: لا توجد متابعة للبوابة أو الهاتف؛ أي نشر إلى بيئة خارجية يحتاج موافقة مستقلة.

### 2026-07-22 — M033 مركز الشحن ومركز العملاء الداخليان

- تغيير المصدر: جُمعت روابط التتبع وأسعار الشحن وقائمة الشحنات في وجهة إدارية واحدة باسم `Shipping Center / مركز الشحن`، وتحولت معاينة العميل الإدارية إلى `Customer Center / مركز العملاء` مع ملخص مجمع عند عدم اختيار عميل.
- ERP الإداري: `RUNTIME_VERIFIED`؛ يعرض مركز الشحن بطاقات التتبع وقائمة الشحنات والأسعار، ويعرض مركز العملاء إجمالي العملاء والطلبات والشحنات والتنبيهات والمالية حسب كل عملة داخل الكيان الحالي.
- بوابة العميل: `UNCHANGED_VERIFIED`؛ بقي التنقل الخارجي والعرض الخاص بالعميل وعقد البيانات والصلاحيات كما هي، ولا يظهر الملخص الإداري أو مصطلح مركز الشحن للعميل الخارجي.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ التغيير خاص بتنقل الموظفين وملخص الإدارة، ولا يغير شاشة العميل أو API أو البيانات المرئية له.
- اعتماد API والبيانات: لا تغيير في `/api/mobile/v1` أو عقد الجلسة. التجميع المالي إداري فقط ولا يجمع العملات المختلفة.
- أثر التصميم والمصطلحات: تقليل ازدحام قائمة الإدارة، واستخدام بطاقات تشغيل متجاوبة وأهداف لمس واضحة؛ لا تغيير في المصطلحات الظاهرة للعميل أو تصميم تطبيق الهاتف.
- التحقق على الهاتف: مركز العملاء ومركز الشحن `PASS` عند 390×844 دون overflow أو تحذيرات PHP؛ بوابة العميل الخارجية فُحصت منفصلة وبقيت دون عناصر الإدارة الجديدة.
- الحالة: `ADMIN_RUNTIME_VERIFIED`, `PORTAL_UNCHANGED_VERIFIED`, `MOBILE_NOT_APPLICABLE`.
- المتابعة: لا توجد متابعة هاتفية لهذه المهمة الداخلية.

### 2026-07-22 — تصحيح فتح تقارير الإدارة دون عميل محدد

- تغيير المصدر: صفحة التقارير الإدارية تعرض محدد العميل وحالة إرشادية عندما لا يحتوي الرابط على `socid` بدل إظهار منع وصول خاطئ.
- ERP الإداري: `RUNTIME_VERIFIED`؛ لا تُحمّل بيانات تقارير حتى يختار الموظف عميلًا صالحًا. تم فتح الصفحة بجلسة Admin فعلية واختيار `China Gate` دون منع وصول.
- بوابة العميل: `NOT_APPLICABLE` وظيفيًا؛ بقي العميل الخارجي مقيدًا بشركته من الجلسة وتظهر له تقاريره المنشورة فقط.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ لا يوجد تغيير في عقد العميل أو API أو تصميم شاشة التقارير.
- اعتماد API والبيانات: لا تغيير؛ التعديل يخص حالة تنقل الموظف الداخلية فقط.
- أثر التصميم والمصطلحات: استخدم محدد العميل ونمط الفراغ المشتركين في الإدارة.
- التحقق على الهاتف: `PASS` عند 390×844 دون overflow أفقي، مع ظهور محدد العميل وزر العرض وحالة الفراغ بصورة قابلة للاستخدام. لا تغيير مطلوب في بوابة العميل أو التطبيق.
- الحالة: `ADMIN_RUNTIME_VERIFIED`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`.
- المتابعة: لا توجد متابعة هاتفية لهذا التصحيح المحدود.

### 2026-07-22 — تصحيح تجربة الإدارة وبوابة العميل في M032

- تغيير المصدر: وُحدت تسميات تنقل الإدارة، وحُذف ادعاء `LIVE` الافتراضي، وحُصر إنشاء الشحنة في سياقه. في بوابة العميل أصبح الحساب `Financial Account` مع اختصار آمن للملف الشخصي، وأصبحت تصفية وقائمة الشحنات متجاوبة بأهداف لمس مناسبة.
- ERP الإداري: `SOURCE_AND_TEST_VERIFIED`؛ اجتازت عقود التنقل والتقارير وSOP وCSS الاختبارات. المعاينة المرئية الموثقة ما زالت `PENDING` لعدم وجود جلسة Admin محلية مفتوحة.
- بوابة العميل: `PORTAL_VERIFIED` بجلسة عميل خارجية فعلية. اجتازت قائمة الشحنات سطح المكتب 1280px والهاتف 390×844 و430×932 دون overflow أو تحذيرات PHP، واجتاز الحساب المالي الهاتف مع رابط ملف شخصي واضح.
- تطبيق الهاتف: `PENDING` وخارج نطاق M032. المتابعة المطلوبة هي مواءمة تسمية الحساب، وإضافة/تأكيد وصول الملف الشخصي، وتطبيق تجربة تصفية الشحنات الأصلية للهاتف مع أهداف لمس وحالات تحميل وفراغ وخطأ مناسبة.
- اعتماد API والبيانات: لا تغيير في API أو البيانات. يجب أن يظل التطبيق مشتقًا من جلسة العميل وعقد `/api/mobile/v1`، لا من معاملات صلاحية يرسلها العميل.
- أثر التصميم والمصطلحات: استخدام مصطلحات `Financial Account` و`Profile` وتجربة تصفية ذات تسلسل واضح، مع الحفاظ على العربية RTL والإنجليزية LTR وألوان العلامة وبطاقات الحالة المشتركة.
- التحقق على الهاتف: الويب `PASS` عند 390px و430px. قبول التطبيق على Android وiPhone ما زال مطلوبًا في مهمة هاتف مستقلة.
- الحالة: `ADMIN_SOURCE_VERIFIED`, `ADMIN_RUNTIME_PENDING`, `PORTAL_VERIFIED`, `MOBILE_PENDING`.
- المتابعة: فتح جلسة Admin محلية للفحص المرئي، ثم إنشاء مهمة هاتف معتمدة دون توسيع M032.

### 2026-07-22 — مصطلحات البوابة والمستندات والتقارير والمالية في M030/M031

- تغيير المصدر: عُممت التسميات والتنقل الظاهر للعميل؛ أُخفي تنقل مستندات التصدير القديم؛ جُمعت تجربة المستندات حول مستندات وملفات الشحن الظاهرة للعميل؛ أصبحت التقارير عامة؛ أصبح المركز المالي وجهة المالية الأساسية؛ وعُممت صياغة مراحل المشروع.
- ERP الإداري: يستخدم مصطلحات عامة للفواتير والمستندات والتقارير؛ يحتفظ بأدوات الإدارة الداخلية والبيانات القديمة عند الحاجة؛ المركز المالي هو الوجهة الأساسية والتخصيص إجراء إداري ثانوي.
- بوابة العميل: تستخدم `Invoices` و`Documents` و`Reports` بمفهوم عام؛ تخفي قسم المستندات التجارية أو مستندات التصدير القديم عن العميل الخارجي؛ تعرض مستندات وملفات الشحن المعتمدة بتنسيق مناسب للهاتف يحافظ على قراءة معلومات الملف ويضع الإجراءات أسفلها؛ وتستخدم صياغات عامة لمراحل المشروع مثل اكتمال المستندات وانتهاء العملية.
- تطبيق الهاتف: `PENDING`. يجب مراجعة ومواءمة `app/documents.tsx` و`app/reports.tsx` و`app/service-invoices.tsx` وتسميات مراحل المشروع وتسميات التنقل وأي مسارات قديمة للمستندات التجارية. لا تُحذف المسارات القديمة قبل أن تؤكد مهمة هاتف معتمدة الترحيل والتوافق العكسي.
- اعتماد API والبيانات: التأكد من أن `/api/mobile/v1` يعرض مجموعة المستندات نفسها المسموح بها للعميل، والمعنى العام نفسه للفواتير والتقارير، وملكية العميل والكيان، وعقد التنزيل الآمن المستخدم في البوابة.
- أثر التصميم والمصطلحات: تطبيق التسلسل البصري نفسه للعلامة والبطاقات وألوان الحالات وحالات الفراغ، مع العربية RTL افتراضيًا والإنجليزية LTR. يجب التفاف أسماء المستندات الطويلة إلى كلمات أو مقاطع مقروءة، ووضع إجراءات التنزيل والرد كأهداف لمس بعرض كامل أسفل معلومات الملف. يجب أن تتبع التخطيطات المتجاوبة عرض الحاوية المتاح عبر شبكات مرنة، لا تصحيحات منفصلة لكل جهاز. تُستخدم أنماط التنقل واللمس الأصلية للهاتف بدلًا من نسخ تخطيط سطح المكتب.
- التحقق على الهاتف: تتطلب تغييرات ويب الإدارة والعميل فحصًا عند 390 بكسل؛ وما زال تنفيذ الهاتف يحتاج إلى قبول على أجهزة Android وiPhone.
- الحالة: `ADMIN_VERIFIED`, `PORTAL_VERIFIED`, `MOBILE_PENDING`.
- المتابعة: إنشاء مهمة توافق هاتف معتمدة قبل تغيير مسارات الهاتف أو عقد API. يجب مطابقة عدد المستندات في لوحة التحكم مع مجموعة البيانات الفعلية الظاهرة في شاشة المستندات.

### 2026-08-05 — M043 استقبال الموقع العام وأسعار FCL

- تغيير المصدر: أضيف مسار عام مستقل في React لبحث أسعار FCL المنشورة وإرسال طلب حجز، وأصبحت الاستفسارات وطلبات الكتالوج تدخل قائمة استقبال داخلية في Dolibarr عبر بوابة Node محمية.
- ERP الإداري: `SOURCE_AND_DB_CONTRACT_VERIFIED_LOCAL`؛ جدول الاستقبال والصلاحيات والتصنيف والتحويل اليدوي إلى Operations موجودة في المصدر. نجح إنشاء الطلب والتكرار الآمن وتعارض الحمولة المتغيرة ولقطة السعر في MariaDB المحلية. قبول شاشة الموظف الفعلية ينتظر تثبيت الفرع في runtime المحلي.
- بوابة العميل الموثقة: `NOT_APPLICABLE`؛ لم يتغير M035 أو M038 أو أي مسار أو بيانات ظاهرة للعميل المسجل. العملية المحولة تبدأ دائمًا `customer_visible=0`.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ لم يتغير `/api/mobile/v1` أو أي شاشة أو صلاحية هاتف.
- اعتماد API والبيانات: الموقع العام لا يختار `entity` أو `fk_soc` ولا يستقبل تكلفة أو هامشًا أو معرّف rate. الرمز المشفر يعاد التحقق منه داخل Dolibarr، والطلب لا يعني تأكيد مساحة أو SO.
- أثر التصميم والمصطلحات: العربية RTL افتراضيًا مع الإنجليزية والفرنسية LTR؛ المصطلح المعتمد هو `طلب حجز / Booking request` وليس حجزًا مؤكدًا.
- التحقق على الهاتف: صفحة React العامة نجحت محليًا عند 430×932 و390×844 بلا overflow، مع حفظ الحقول والمرجع عند تبديل اللغة. لا قبول تطبيق أصلي مطلوب.
- الحالة: `PUBLIC_SITE_LOCAL_VERIFIED`, `ADMIN_RUNTIME_UI_PENDING`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`, `STAGING_NOT_DEPLOYED`, `PRODUCTION_NOT_DEPLOYED`.

## قاعدة الإكمال

لا تُعد المهمة المرتبطة مكتملة التوافق حتى يسجل هذا الملف نتيجة الواجهات الثلاث. يجوز تأجيل واجهة فقط عندما تكون خارج النطاق المعتمد، ويجب تسجيلها هنا بالحالة `PENDING` مع متابعة دقيقة.

### 2026-07-25 — M036 استيراد بيانات الكرتون من WooCommerce إلى Dolibarr

- تغيير المصدر: لا يوجد تغيير في كود التطبيق أو البوابة. أُضيفت سبعة Product Extra Fields في Dolibarr وربطت بمفاتيح `_b2bclp_*` الموجودة في WooCommerce من خلال إعدادات ECommerceNG القياسية.
- ERP الإداري: `RUNTIME_VERIFIED_PRODUCTION`؛ نجحت مزامنة المنتج `105789` فقط وأنشأت/ربطت المنتج `WP-9688B` بالقيم `100` وحدة/كرتون، `50 × 40 × 38 cm`، صافي `11 kg`، إجمالي `12 kg`، مع بقاء CBM المباشر فارغًا مطابقًا للمصدر. الحقل المحسوب من الأبعاد يساوي `0.076 m³`.
- بوابة العميل: `NOT_APPLICABLE`؛ لم تُعرض الحقول الجديدة ولم يتغير أي مسار أو صلاحية أو مستند أو مصطلح للعميل.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ لم يتغير `/api/mobile/v1` أو أي شاشة أو عقد بيانات.
- اعتماد API والبيانات: WooCommerce هو مصدر القيم السبع؛ لم يُنشأ API جديد. يجب على Customer Order Center المستقبلي حساب CBM من الأبعاد أو استخدام قيمة CBM مخزنة، وعدم تحويل الفراغ إلى صفر.
- أثر التصميم والمصطلحات: لا يوجد تغيير مرئي للعميل. الحقول الجديدة إدارية فقط في هذه المهمة.
- التحقق على الهاتف: `NOT_APPLICABLE` لعدم وجود تغيير في أي صفحة ويب موجهة للعميل أو شاشة هاتف.
- الحالة: `ADMIN_RUNTIME_VERIFIED_PRODUCTION`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`.
- المتابعة: يمنع توسيع المزامنة إلى كامل الكتالوج دون موافقة مستقلة واختبار جودة بيانات يشمل المنتجات ذات الأبعاد، CBM المباشر، القيم الفارغة، والـvariations.

### 2026-07-25 — M038 مخطط تحميل الحاوية وطلب B2B التجريبي

- تغيير المصدر: أضيفت خدمة حساب عامة داخل `chinaplanetlogistics`، ومسار تخطيط مشترك للمستندات المحفوظة، وواجهة عميل محلية لإنشاء طلب مسودة من المنتج التجريبي `105789` / Dolibarr `738`.
- ERP الإداري: `LOCAL_VERIFIED`؛ سُجلت hook contexts لعروض الأسعار وطلبات البيع وطلبات الشراء، وتستخدم المحولات نفس كمية القطع وحقول المنتج لحساب الكراتين وCBM والوزن. لم يُعدّل Dolibarr core.
- بوابة العميل: `LOCAL_VERIFIED`؛ أدخل Alpha عشرة كراتين فأنشأ النظام الطلب المحلي `(PROV87)` للعميل نفسه بكمية `1,000` قطعة وحالة Draft. بقيت الخطة `10 cartons / 0.760 m³ / 120 kg / 20GP`، ورُفض وصول Beta للسجل.
- تطبيق الهاتف: `PENDING`؛ لا توجد في M038 شاشة إنشاء طلب أو WebView/Canvas أو عقد mobile API جديد. المتابعة المطلوبة هي Mission مستقلة تضيف عرض خطة القراءة أولًا ثم إنشاء طلب الكراتين فقط بعد تثبيت snapshot/API آمن، مع اشتقاق `entity` و`fk_soc` من الجلسة.
- اعتماد API والبيانات: لا API جديد في هذه المهمة. المصدر الحالي ديناميكي من Product Extra Fields، والطلب القياسي يخزن القطع. يلزم قبل أي إصدار live snapshot ثابت لبنود التعبئة حتى لا تتغير خطة الطلب القديم عند تعديل المنتج.
- أثر التصميم والمصطلحات: واجهة عربية RTL مع تسميات إنجليزية، بطاقات navy/cyan، حالة تقديرية واضحة، Canvas قابل للوحة المفاتيح وملخص نصي لغير المعتمدين على الرسم.
- التحقق على الهاتف: `LOCAL_VERIFIED` عند `390px` و`430px` لصفحة إنشاء الطلب وصفحة تفاصيل الطلب؛ لا overflow أفقي، والأزرار والبطاقات والـCanvas بقيت داخل العرض.
- الأمان والخصوصية: `PASS` محليًا لعزل Alpha/Beta وCSRF وallowlist المنتج والسعر المشتق من Dolibarr؛ لا supplier/cost/margin/private data في payload العميل.
- الحالة: `ADMIN_LOCAL_VERIFIED`, `PORTAL_LOCAL_VERIFIED`, `MOBILE_PENDING`, `LIVE_NOT_DEPLOYED`, `STAGING_NOT_DEPLOYED`.

### 2026-07-24 — M037-C04 تصنيف طلبات الشحن المتوافق مع MariaDB

- تغيير المصدر: نُقل تصنيف حالات طلبات الشحن من تعبيرات JSON داخل MariaDB إلى فك JSON وتصنيفه في PHP، مع اعتبار JSON القديم غير الصالح بيانات خدمة فارغة وحالة نشطة آمنة.
- ERP الإداري: `BACKEND_VERIFIED_LOCAL`؛ أعداد Total/Active/Confirmed/Closed/Cancelled والفلاتر تستخدم التصنيف نفسه، مع بقاء نطاق `entity` و`fk_soc` وفحوص ملكية الحجز والشحنة دون تغيير.
- بوابة العميل: `NOT_APPLICABLE`؛ لا تغيير في الصفحات أو المصطلحات أو عقد البيانات أو الملفات أو الإجراءات المرئية للعميل.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ لا تغيير في `/api/mobile/v1` أو تصميم التطبيق أو البيانات المتاحة له.
- اعتماد API والبيانات: لا يوجد عقد جديد. التصحيح يقرأ فقط حقول الخدمة الداخلية الموجودة ويمنع فشل الاستعلام بسبب JSON قديم غير صالح.
- أثر التصميم والمصطلحات: لا يوجد تغيير مرئي أو مصطلحي.
- التحقق على الهاتف: `NOT_APPLICABLE` لأن التغيير backend داخلي فقط؛ بقي عقد الاستجابة والتصميم دون تغيير، واجتاز عقد المصدر المتجاوب الحالي.
- الحالة: `ADMIN_BACKEND_VERIFIED_LOCAL`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`.
- المتابعة: أي نشر خارجي يحتاج artifact جديدًا مقفولًا وموافقة نشر مستقلة.

### 2026-07-23 — M035 أسعار الشحن الفعلية والحجز المباشر للعميل

- تغيير المصدر: أضيف بحث عميل موثق لأسعار FCL المنشورة الحالية، نتيجة سعر بيع آمنة، نموذج حجز مباشر، إعادة تسعير من الخادم، snapshot تجاري ثابت، منع تكرار بقاعدة البيانات، وربط ذري بطلب `shipping_booking` واحد في Operations.
- ERP الإداري: `RUNTIME_VERIFIED_LOCAL`؛ ظهر الطلب في Operations مع مرجع دائم، مهام الحجز، وكتلة `Accepted customer price / السعر المقبول` غير قابلة للاستبدال تعرض المسار والمعدات وسعر الوحدة والكمية والإجمالي والعملة والصلاحية. اجتاز 1440 و1024 و768 و390 و320 دون overflow.
- بوابة العميل: `PORTAL_VERIFIED_LOCAL`؛ يظهر الآن رابط مستقل `أسعار الشحن / Shipping Prices` بجوار الشحنات، وله حالة نشطة مستقلة ويفتح شاشة السعر الفعلي والحجز المباشر. بحث حقيقي Guangzhou → SOKHNA / 40HQ أعاد 7 أسعار بيع فعلية، وعرض الصلاحية والنطاق والمشمول والمستثنى، وحسب `سعر الوحدة × العدد`، وأنشأ الحجز وعرض المرجع والحالة الآمنة. اجتازت العربية RTL والإنجليزية LTR مقاسات 1920 و1440 و1280 و1024 و768 و430 و390 و360 و320 دون overflow؛ وأعيد فحص القائمة عند 390 و320 بعد تصحيح الرابط.
- تطبيق الهاتف: `PENDING` وخارج نطاق M035. يلزم Mission هاتف مستقلة لإضافة API عميل آمن للبحث والحجز والحالة مع projection allowlist واشتقاق `entity/fk_soc` من الجلسة وidempotency وواجهة عربية/إنجليزية أصلية.
- اعتماد API والبيانات: لا يوجد API هاتف جديد في M035. مصدر السعر هو Shipping Prices المنشور؛ `entity` و`fk_soc` من جلسة الويب؛ والسجل التشغيلي هو Operations. يمنع عرض التكلفة والهامش ومصدر الدفعة ومعرّفات القواعد والـforwarder والملاحظات الخاصة.
- أثر التصميم والمصطلحات: المصطلح المعتمد `Actual price / السعر الفعلي` ثم `Direct booking / الحجز المباشر`؛ النجاح يعني `Received / تم الاستلام` ولا يعني تأكيد الناقل أو SO. أصبحت نتائج الويب بطاقتين مدمجتين في الصف على سطح المكتب وبطاقة واحدة على الهاتف، بهوية كحلي/ذهبي/أبيض مشتركة، وعزل اتجاه للمسار والسعر ومعادلة `الإجمالي USD = سعر الوحدة USD × العدد`. حُذف Gross Weight وCBM من طلب الحجز وأصبح Notify Party اختياريًا؛ يجب أن يتبع عقد الهاتف المستقبلي الحقول نفسها. العربية افتراضية RTL والإنجليزية LTR، مع أهداف لمس 44px وحقول هاتف 16px وحالات فراغ وخطأ ونجاح حية.
- التحقق على الهاتف: بوابة العميل والحوار `PASS` حتى 320px؛ Admin Operations `PASS` عند 390px و320px؛ لا قبول تطبيق أصلي ضمن هذه المهمة.
- الحالة: `ADMIN_RUNTIME_VERIFIED_LOCAL`, `PORTAL_VERIFIED_LOCAL`, `MOBILE_PENDING`.
- المتابعة: إنشاء Mission للهاتف وAPI بعد اعتماد عقد M035، وعدم إرسال سعر أو عميل أو كيان من التطبيق كمصدر صلاحية.

### 2026-07-22 — متابعة تدقيق UX عبر الواجهات

- تغيير المصدر: لم يحدث أي تغيير في كود الوظائف؛ راجع تدقيق UX للقراءة فقط مسارات العميل والتنقل المشترك وأحجام التفاعل ومصطلحات الإدارة والاتساق بين الواجهات.
- ERP الإداري: `RUNTIME_PENDING`. يوضح المصدر ولقطات الشاشة المقدمة ازدواج التنقل والتباس تسمية `LIVE` وعدم تطابق المصطلحات والوجهات والمبالغة في إظهار إجراء إنشاء شحنة جديدة عالميًا. ما زال القبول الموثق على سطح المكتب واللوحي والهاتف مطلوبًا.
- بوابة العميل: `PORTAL_AUDITED`. تلائم تسعة مسارات أساسية مقاس 390×844 دون تحذير PHP ظاهر، لكن أهداف اللمس وارتفاع حقل حالة الشحنة وصياغة الطلبات والدعم وإمكانية اكتشاف الحساب والملف الشخصي تحتاج إلى مهام تصحيح.
- تطبيق الهاتف: `PENDING`. بعد اعتماد تصحيحات البوابة، يجب مطابقة العقد النهائي للطلبات والحساب والملف الشخصي والمستندات والتقارير والفواتير والحالة والتصفية وأهداف اللمس وحالات الفراغ باستخدام عناصر تحكم أصلية للهاتف.
- اعتماد API والبيانات: لم يحدث تغيير في API. يجب أن يستمر عمل الهاتف المستقبلي في اشتقاق نطاق العميل والكيان من الجلسة الموثقة، واستخدام مجموعات بيانات البوابة المعتمدة نفسها بدقة.
- أثر التصميم والمصطلحات: الحفاظ على خريطة مصطلحات واحدة وتسلسل مشترك للعلامة والحالات في الإدارة والبوابة والهاتف. يجب أن تلتزم أهداف اللمس الأصلية بإرشادات المنصة، وأن تلتف المراجع وأسماء الملفات الطويلة دون تكوين أعمدة من حرف واحد.
- التحقق على الهاتف: تم فحص مسارات ويب العميل عند 390×844. ما زال فحص الإدارة على الهاتف واللوحي والقبول على جهاز أصلي معلقًا.
- الحالة: `PORTAL_AUDITED`, `ADMIN_RUNTIME_PENDING`, `MOBILE_PENDING`.
- المتابعة: إنشاء مهام تصحيح محدودة، ثم تسجيل نتائج الإدارة والعميل والهاتف المتحقق منها بصورة منفصلة؛ لا يُضم تنفيذ الهاتف بصمت إلى تصحيح خاص بالويب.

### 2026-08-03 — M041 ترجمة ملاحظات وكيل الشحن إلى الإنجليزية تلقائيًا

- تغيير المصدر: عند رفع ملف أسعار الشحن، يحتفظ النظام بالنص الخام والمطبع داخليًا، ويترجم الملاحظة الصينية إلى الإنجليزية من خلال مزوّد مضبوط بمتغيرات بيئة الخادم. تُقبل النتيجة الصحيحة تلقائيًا بلا اعتماد لكل سطر، ويمكن للموظف المخوّل تعديل الإنجليزية قبل النشر أو بعده. فشل الترجمة يبقى خاصًا ويمنع نشر الدفعة حتى إدخال الإنجليزية يدويًا.
- ERP الإداري: `LOCAL_RUNTIME_VERIFIED`؛ نجح رفع ملف CSV حقيقي بملاحظات صينية، وظهرت الحالة `Automatically translated` مع زر `Save English translation`. نجح تعديل النص في المسودة ثم تعديله مرة أخرى بعد النشر، وتحديث كل صفوف الحاويات الناتجة من صف المصدر نفسه.
- بوابة العميل: `LOCAL_RUNTIME_VERIFIED`؛ عرض بحث السعر النص الإنجليزي المصحح فقط داخل `lang=en` و`dir=ltr` بالعربية RTL والإنجليزية LTR. لم يظهر النص الصيني أو مزوّد الترجمة/الخطأ أو تكلفة المورد أو الهامش أو الملاحظات الداخلية.
- تطبيق الهاتف: `PENDING`؛ لا يوجد في M041 مسار أسعار شحن داخل `/api/mobile/v1` ولا شاشة أسعار/حجز أصلية. عند تنفيذ مهمة الهاتف المؤجلة لـM035 يجب استهلاك `public_notes` الإنجليزية فقط ومنع مفاتيح المصدر والـprovider/error/cost/margin في عقد API.
- اعتماد API والبيانات: عقد الويب الخارجي بقي allowlist ويعرض `public_notes` فقط. لا API هاتف جديد ولا صلاحية أو `entity`/`fk_soc` يختارها العميل.
- أثر التصميم والمصطلحات: المصطلح الإداري `Automatically translated` ثم `Staff corrected` عند التعديل؛ حالات الفشل تظهر `Needs attention`. يظهر للعميل العنوان الثنائي `ملاحظات الناقل بالإنجليزية / Carrier note in English` والنص نفسه LTR متعدد الأسطر.
- التحقق على الهاتف: `PASS` لصفحة الإدارة وبوابة العميل عند 390px و360px بلا overflow أفقي؛ فُحصت العربية RTL والإنجليزية LTR. قبول تطبيق أصلي غير مطلوب داخل M041.
- الحالة: `ADMIN_LOCAL_RUNTIME_VERIFIED`, `PORTAL_LOCAL_RUNTIME_VERIFIED`, `MOBILE_PENDING`, `LIVE_PROVIDER_NOT_CONFIGURED`, `STAGING_NOT_DEPLOYED`, `PRODUCTION_NOT_DEPLOYED`.
- المتابعة: ضبط endpoint/model/key لمزوّد ترجمة الإنتاج عبر بيئة الخادم في مهمة نشر مستقلة، ثم إضافة عقد الهاتف الآمن في مهمة الهاتف الخاصة بأسعار الشحن والحجز.

### 2026-08-06 — M043-C01 زر طلبات الموقع في لوحة الإدارة

- تغيير المصدر: أضيفت وجهة `Website Requests / طلبات الموقع` إلى قائمة لوحة الإدارة المشتركة وإلى `Quick Actions` دون إنشاء أيقونة Dolibarr علوية جديدة.
- ERP الإداري: `STAGING_AUTHENTICATED_BROWSER_VERIFIED` على `backup-erp`؛ الزران يفتحان سجل الطلبات الحالي، ونجح فتح صفحة الطلب `id=14` دون تغيير السجل.
- بوابة العميل: `NOT_APPLICABLE`؛ لا تغيير في بوابة العميل أو التنقل أو البيانات أو الصلاحيات الخارجية.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ التغيير داخل لوحة الموظفين فقط ولا يغير `/api/mobile/v1` أو أي شاشة أصلية.
- اعتماد API والبيانات: لا API أو schema أو صلاحية جديدة؛ استمر المسار في تطبيق صلاحية قراءة الطلبات الموجودة.
- أثر التصميم والمصطلحات: تسمية ثنائية اللغة متسقة داخل التنقل والإجراءات السريعة، مع بقاء أيقونة Customer Center العلوية الوحيدة كما هي.
- التحقق على الهاتف: `PASS` عند 390×844؛ ظهر زر الطلبات داخل القائمة المتجاوبة المفتوحة دون overflow مادي.
- الحالة: `ADMIN_STAGING_VERIFIED`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`, `PRODUCTION_NOT_ACCESSED`.
- المتابعة: لا متابعة للبوابة أو الهاتف؛ أي نشر production يحتاج موافقة ومهمة نشر مستقلة.

### 2026-08-08 — M044-C01 تصحيح اعتماد صفحة استيراد أسعار الشحن

- تغيير المصدر: أُضيف ملف خدمة الترجمة الناقص الذي يستدعيه مستورد أسعار الشحن الموجود؛ لم يتغير المستورد أو صفحة الاستيراد أو عقد البيانات.
- ERP الإداري: `BACKUP_ERP_AUTHENTICATED_VERIFIED`؛ تعمل صفحة الاستيراد على سطح المكتب و`390×844` وتعرض اختيار الملف والوكيل وإعداد المسودة وسجل الدفعات دون Fatal Error أو overflow أفقي.
- بوابة العميل: `NOT_APPLICABLE`؛ لا تغيير في المسارات أو البيانات أو الصلاحيات أو الملاحظات العامة للعميل.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ لا تغيير في `/api/mobile/v1` أو أي شاشة أو عقد بيانات أصلي.
- اعتماد API والبيانات: لا API أو schema أو سجل أسعار جديد؛ لم يُرفع ملف أسعار أثناء القبول.
- أثر التصميم والمصطلحات: لا تغيير تصميمي؛ التصحيح يعيد تحميل صفحة الموظف الموجودة فقط.
- التحقق على الهاتف: صفحة الإدارة `PASS` عند `390×844` بعرض داخلي وتمرير أفقي متطابقين `390/390`؛ لا قبول لتطبيق أصلي مطلوب.
- الحالة: `ADMIN_BACKUP_ERP_VERIFIED`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`, `PRODUCTION_NOT_ACCESSED`.

### 2026-08-09 — M044-C04 ترجمة ملاحظات الشحن تلقائيًا بمزوّد مجاني

- تغيير المصدر: أصبح MyMemory هو fallback مجاني بلا مفتاح عند غياب مزوّد خاص، وتُعتمد الترجمة الإنجليزية الصحيحة تلقائيًا بعد فحص اللغة والأرقام والأكواد. صفحة الدفعة تعيد محاولة الملاحظات المعلقة تلقائيًا عبر POST محمي وعلى دفعات محدودة، ويبقى تعديل الموظف اختياريًا.
- ERP الإداري: `LOCAL_IMPLEMENTED_TEST_PENDING`؛ يتأثر استيراد أسعار الشحن وصفحة مراجعة الدفعة فقط. لا نشر أسعار داخل هذه المهمة.
- بوابة العميل: `NOT_APPLICABLE`؛ عقد العرض لم يتغير ويستمر في إظهار `public_notes` المعتمدة فقط، ولا يظهر النص الصيني أو المزوّد أو الخطأ.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ لا تغيير في `/api/mobile/v1` أو الشاشات الأصلية.
- اعتماد API والبيانات: يُرسل نص ملاحظة الناقل المطبع وحده إلى MyMemory مع `zh-CN|en`؛ لا تُرسل أسعار أو بيانات عميل أو مورد أو ملف Excel أو بيانات ERP أخرى. وافق Ahmed صراحة على هذا الإرسال المؤقت.
- أثر التصميم والمصطلحات: حُذفت صياغة وجوب اعتماد كل ترجمة يدويًا. تظهر حالة الترجمة التلقائية والتوقف الآمن عند حد الخدمة، مع زر إعادة محاولة لا يُعد اعتمادًا أو نشرًا.
- التحقق على الهاتف: `PENDING_LOCAL` لصفحة الإدارة عند 390px بعد اكتمال الاختبارات؛ البوابة والتطبيق غير متأثرين.
- الحالة: `ADMIN_LOCAL_IMPLEMENTED_TEST_PENDING`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`, `STAGING_NOT_DEPLOYED`, `PRODUCTION_NOT_ACCESSED`.

### 2026-08-09 — M046-C01 تعطيل ترجمة ملاحظات الشحن بسياسة الإصدار

- تغيير المصدر: أصبح قرار الترجمة الفعلي ثابتًا ومركزيًا في خدمة الترجمة ويعيد `disabled` قبل قراءة إعدادات المزوّد أو تشغيل النقل. يرفض مسارا الترجمة التلقائية والتعديل اليدوي قبل أي استعلام أو transaction، وأزيل مفتاح التفعيل ومسار كتابة الثابت من صفحة الإعدادات.
- ERP الإداري: `ADMIN_SOURCE_AND_FOCUSED_TESTS_VERIFIED_LOCAL`؛ يحتفظ المستخدم الداخلي المخول بصلاحيتَي Import وView Cost أو المدير بعرض ملاحظات المصدر وبيانات provenance للقراءة فقط. لا يظهر نموذج ترجمة أو زر أو auto-submit عند التعطيل، ولا تمنع الملاحظات استيراد الدفعة أو نشرها أو تفعيلها.
- بوابة العميل: `NOT_APPLICABLE` وظيفيًا؛ لم يتغير المسار أو التصميم أو الصلاحيات. أضيف اختبار إسقاط يثبت حجب ملاحظات M041 المستوردة حتى لو كان الثابت المخزن `0` أو `1` والحالة `approved`، مع استمرار ظهور الملاحظة العامة المشروعة للسعر اليدوي ذي `source_notes IS NULL`.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ لا تغيير في `/api/mobile/v1` أو أي شاشة أو عقد بيانات أصلي.
- الموقع العام React وNode وWordPress: `NOT_APPLICABLE`؛ لا تغيير في المصدر أو العقد أو النشر.
- اعتماد API والبيانات: لا API أو schema أو سجل أعمال جديد، ولا يخرج نص ملاحظة أو إعداد مزوّد من Dolibarr. فحوص النقل المحقون أثبتت `PROVIDER_CALLS=0`، وفحوص خدمة المراجعة أثبتت عدم بدء استعلام أو transaction عند الرفض.
- أثر التصميم والمصطلحات: الرسالة المعتمدة توضح أن الترجمة متوقفة بسياسة الإصدار، وأن المراجعة الداخلية للقراءة فقط، وأن ملاحظات المصدر لا تظهر للعملاء ولا تمنع نشر الأسعار.
- التحقق على الهاتف: `SOURCE_CONTRACT_ONLY` لصفحة الإدارة؛ قبول المتصفح المحلي/المرحلي عند `390px` مؤجل إلى بوابة staging المنفصلة وغير المصرح بها في هذه المهمة.
- الحالة: `LOCAL_RUNTIME_AND_TESTS_VERIFIED`, `RELEASE_ASSETS_PENDING`, `ADMIN_ONLY`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`, `PUBLIC_REACT_NOT_APPLICABLE`, `STAGING_NOT_ACCESSED`, `PRODUCTION_NOT_ACCESSED`.

### 2026-08-13 — M054 ملكية مزامنة المشاريع مع Kanban

- تغيير المصدر: نُقل تنفيذ مزامنة المشروع وبطاقة Kanban إلى وحدة CP Kanban المالكة، مع إبقاء مسار Customer Center القديم كغلاف توافق فقط.
- ERP الإداري: `SOURCE_VERIFIED_BEHAVIOR_UNCHANGED`؛ لا تغيير في المسارات أو الأعمدة أو الحالات أو الصلاحيات أو واجهة المستخدم.
- بوابة العميل: `NOT_APPLICABLE` وظيفيًا؛ لا تغيير في الإسقاط أو التنقل أو العرض للعميل، وتظل المزامنة التلقائية الكتابية متوقفة للمستخدم الخارجي.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ لا تغيير في `/api/mobile/v1` أو أي شاشة أو عقد بيانات أصلي.
- اعتماد API والبيانات: لا API أو schema أو سجل أعمال جديد، وبقيت شروط الكيان والعميل في التنفيذ المنقول.
- أثر التصميم والمصطلحات: لا تغيير.
- التحقق على الهاتف: غير مطلوب؛ لا تغيير مرئي. اختبار المصدر الخاص بمنع الكتابة للعميل بقي ضمن تغطية M010 حتى شرط CSS baseline غير المتعلق بالمهمة.
- الحالة: `ADMIN_SOURCE_VERIFIED`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`, `STAGING_NOT_ACCESSED`, `PRODUCTION_NOT_ACCESSED`.

### 2026-08-13 — M055 الرد على طلبات الموقع بالبريد وWhatsApp

- تغيير المصدر: أضيف داخل تفاصيل طلب الموقع محرر بريد للموظف وزر WhatsApp يعتمد على البريد والهاتف المحفوظين في الطلب؛ لا يتغير عقد استقبال الطلب من الموقع.
- ERP الإداري: `IMPLEMENTED_AND_SOURCE_VERIFIED_LOCAL`؛ المستلم ثابت من الخادم، والمرسل من إعداد Dolibarr، والإرسال محمي بصلاحيتي تعديل الطلب والبريد وCSRF، ويسجل حدثًا في Agenda. عند وجود عرض سعر يستخدم تتبع العرض القياسي.
- بوابة العميل: `NOT_APPLICABLE`؛ لا تغيير في المسارات أو الإسقاط أو واجهة العميل.
- تطبيق الهاتف: `NOT_APPLICABLE`؛ لا تغيير في `/api/mobile/v1` أو أي شاشة أصلية.
- اعتماد API والبيانات: لا API أو schema جديد. رابط WhatsApp يُنشأ فقط لرقم دولي صحيح ويفتح تبويبًا منفصلًا دون إرسال تلقائي أو بيانات اعتماد Meta.
- أثر التصميم والمصطلحات: أضيفت تسميات عربية وإنجليزية وصينية وحالات عدم توفر، وتتكدس الأزرار ونموذج البريد بعرض كامل تحت `700px`.
- التحقق على الهاتف: `SOURCE_CONTRACT_PASS`؛ قبول متصفح فعلي عند `390px` ينتظر موافقة staging مستقلة.
- الحالة: `ADMIN_LOCAL_SOURCE_VERIFIED`, `PORTAL_NOT_APPLICABLE`, `MOBILE_NOT_APPLICABLE`, `STAGING_NOT_ACCESSED`, `PRODUCTION_NOT_ACCESSED`.

### 2026-08-14 — M056 إشعار العميل بالبريد عند تغيير حالة الشحنة

- تغيير المصدر: أضيف خيار صريح للموظف داخل تعديل الشحنة لإرسال بريد بعد الحفظ فقط عندما تتغير الحالة فعليًا ويكون التحديث منشورًا للعميل. يُقرأ المستلم من البريد الرئيسي للعميل داخل نفس الكيان، ويُستخدم مرسل Dolibarr المضبوط، ويُسجل النجاح في Agenda.
- ERP الإداري: `LOCAL_IMPLEMENTED_AND_SOURCE_VERIFIED`؛ الإرسال يتطلب صلاحية تعديل China Planet Logistics وصلاحية Dolibarr القياسية لإرسال الشحنات، ولا يظهر للعميل الخارجي. فشل البريد لا يعيد حالة الشحنة للخلف ويظهر تحذيرًا للموظف.
- بوابة العميل: `BEHAVIOR_UNCHANGED`؛ تستمر في عرض حدث الحالة نفسه الذي حُفظ قبل محاولة البريد. رابط البريد يقود إلى بطاقة الشحنة الموثقة الموجودة، مع استمرار فحص `entity/fk_soc` في الصفحة.
- تطبيق الهاتف: `BEHAVIOR_UNCHANGED`؛ لا تغيير في `/api/mobile/v1` أو الشاشة الأصلية، وتستمر الحالة الحالية في استهلاك نفس بيانات الشحنة. الإشعار هنا Email فقط وليس Push.
- اعتماد API والبيانات: لا API أو schema جديد. البريد يحتوي allowlist من مرجع الشحنة والحالة السابقة والجديدة وETA وسبب التحديث العام ورابط المتابعة؛ لا يحتوي ملاحظات داخلية أو تكلفة أو هامش أو مورد أو ملفات.
- أثر التصميم والمصطلحات: صف إضافي صغير في نموذج تعديل الإدارة فقط: `Send email notification / إرسال إشعار بالبريد`. لا تغيير مرئي في البوابة أو التطبيق.
- التحقق على الهاتف: `STAGING_390PX_PASS` للإدارة؛ ظهر خيار البريد وزر التحديث دون تجاوز أفقي. محاولة الإرسال التجريبية حفظت الحالة وأظهرت فشل البريد وإعادة المحاولة بأمان، لكن إعداد PHP mail في staging منع إثبات Mailpit/Agenda/الرابط.
- الحالة: `ADMIN_STAGING_UI_VERIFIED`, `PORTAL_BEHAVIOR_UNCHANGED`, `MOBILE_BEHAVIOR_UNCHANGED`, `STAGING_SMTP_BLOCKED_SAFE`, `PRODUCTION_RELEASE_APPROVED_PENDING_DEPLOYMENT`.
