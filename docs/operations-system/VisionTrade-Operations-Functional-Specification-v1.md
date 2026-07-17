# Vision Trade Operations System

## المواصفات الوظيفية — الإصدار 1.0

**الشركة:** Vision Trading / China Planet
**النظام:** Dolibarr 23 + ChinaPlanetCustomerCenter + CPKanban + ChinaPlanetLogistics
**حالة الوثيقة:** مسودة وظيفية جاهزة لمراجعة واعتماد أحمد
**تاريخ الإصدار:** 15 يوليو 2026
**مالك القرار التجاري:** Ahmed Hamed
**مبدأ التنفيذ:** Standard Dolibarr أولًا، ثم Extra Fields، ثم تطوير مخصص للفجوات فقط

---

## 1. الهدف

تحويل Dolibarr إلى نظام تشغيل مركزي لكل أعمال الشركة منذ أول تواصل مع العميل وحتى إغلاق العملية، بما في ذلك:

- العملاء وجهات الاتصال.
- سجل المكالمات والرسائل والاجتماعات والتسجيلات.
- طلبات الخدمات وعروض الأسعار.
- أوامر العملاء والموردين.
- التمويل والمدفوعات متعددة العملات.
- البحث عن المنتجات والموردين.
- إدارة الشراء والإنتاج.
- تدقيق المصانع.
- فحص الجودة QC.
- الشحن والمستندات والتسليم.
- المهام الداخلية اليومية.
- التقارير والأدلة والصور والفيديو.
- بوابة العميل.
- المتابعة الإدارية وقياس الاختناقات.

النتيجة المطلوبة: يستطيع الموظف الجديد معرفة **ما العمل المطلوب الآن، لمن، ولماذا، وما الخطوة التالية** دون الاعتماد على الذاكرة أو WhatsApp وحده.

---

## 2. مبادئ إلزامية

1. يوجد مصدر واحد معتمد لكل نوع بيانات.
2. العملية التشغيلية لا تستبدل Project أو Order أو Invoice في Dolibarr؛ بل تربطها.
3. أموال العميل لشراء البضاعة ليست إيراد الشركة.
4. USD وRMB وEGP تظل أرصدة منفصلة ما لم يسجل تحويل عملة رسمي بسعر وتاريخ ومصدر.
5. كل عملية نشطة يجب أن تملك مسؤولًا وإجراءً تاليًا وموعدًا.
6. الملاحظات والملفات الداخلية والعامة منفصلة في قاعدة البيانات، وليس بالـCSS فقط.
7. التقرير لا ينشر للعميل قبل مراجعة واعتماد منفصلين.
8. القرارات المالية، نشر التقارير، نتيجة QC، والإغلاق لا تعتمد على السحب في Kanban وحده.
9. لا حذف للأدلة أو القرارات المنشورة؛ يستخدم الإلغاء المنطقي والإصدارات.
10. كل انتقال أو اعتماد يسجل المستخدم والوقت والحالة السابقة والجديدة والسبب.
11. واجهة العميل مشتقة من بياناته المصرح بها فقط وتتحقق من الملكية على الخادم.
12. لا تعديل لملفات Dolibarr الأساسية.

---

## 3. الوضع الحالي المثبت

### 3.1 الموجود ويمكن إعادة استخدامه

- Third Parties وContacts للعملاء والموردين.
- Proposals وSales Orders وSupplier Orders والفواتير والمدفوعات.
- Projects كحاوية تربط الطلبات والفواتير والموردين والشحنات.
- Operations Center الحالي يحسب دورة: تأكيد الطلب، التمويل، دفع المورد، فاتورة Vision، الشحنة، مستندات التصدير، الإغلاق.
- ChinaPlanetLogistics للشحنات والحاويات وETA.
- Customer Center للبوابة والتمويل والمستندات.
- CPKanban يملك حاليًا: العميل، العمود، المسؤول، الأولوية، التقدم، الموعد، المشروع، الشحنة، ملاحظة عامة وخاصة، سجل حركة، وصورة غلاف محمية.
- طبقة أصول CPKanban تدعم الإصدار وحالة المراجعة والظهور للعميل.

### 3.2 الفجوات

- لا يوجد كائن Operation مستقل متعدد الخدمات.
- لا توجد Service Jobs مستقلة داخل العملية.
- أعمدة Kanban الحالية مصممة أساسًا لدورة الشراء والشحن ولا تصلح لكل الخدمات.
- لا يوجد مدير مهام يومي كامل مبني على Dolibarr Tasks.
- لا يوجد سجل تواصل منظم مع قرار وإجراء تالٍ.
- لا توجد نماذج Factory Audit أو QC/AQL أو خطوط منتجات وعيوب.
- لا يوجد Report Builder بإصدارات واعتماد ونشر.
- الصلاحيات الحالية عامة جدًا: read/write/delete/admin.
- توجد تغييرات محلية كثيرة غير مدمجة؛ يجب جردها وتثبيت baseline قبل أي migration أو تطوير تشغيلي.

---

## 4. نموذج البيانات التجاري

```text
Customer / Third Party
└── Contact(s)
    └── Interaction(s)
        └── Operation
            ├── Service Job 1
            │   ├── Tasks
            │   ├── Checklist
            │   ├── Evidence / Files
            │   ├── Report / Versions
            │   └── Decisions
            ├── Service Job 2
            ├── Proposal / Sales Order / Invoice
            ├── Supplier Orders / Supplier Invoices
            ├── Customer Funding / Allocations
            ├── Shipment(s)
            └── Public Updates / Customer Actions
```

### 4.1 Project

- يظل Dolibarr Project الحاوية التجارية العليا لطلب العميل.
- يمكن لعملية واحدة أن ترتبط بمشروع واحد فقط في المرحلة الأولى.
- يمكن للمشروع أن يحتوي أكثر من عملية عند الحاجة، لكن الافتراضي عملية رئيسية واحدة لكل طلب مستقل.

### 4.2 Operation

يمثل ملف العمل الكامل للعميل، ويحتوي:

- مرجع فريد.
- العميل وجهة الاتصال الأساسية.
- Project المرتبط.
- العنوان والملخص.
- المالك Account Owner.
- مدير العملية Operations Owner.
- الأولوية.
- الحالة العامة.
- تاريخ البداية والموعد المتوقع.
- الإجراء التالي ومسؤوله وموعده.
- خدمات العملية.
- روابط العرض والطلب والفاتورة والمورد والشحنة.
- ملخص داخلي وملخص ظاهر للعميل.

### 4.3 Service Job

يمثل خدمة واحدة مستقلة داخل العملية، مثل Audit أو QC أو Shipping، وله:

- نوع الخدمة وقالبها.
- السعر والعملة وحالة الاتفاق.
- مسؤول التنفيذ والمراجع.
- workflow مستقل.
- SLA مستقل.
- مهام وقائمة تحقق.
- تقارير وأدلة وقرارات.
- حالة مالية مرجعية دون إنشاء دفتر مالي جديد.

### 4.4 Task

- المصدر الأساسي هو Dolibarr Project Task قدر الإمكان.
- CPKanban يعرض وينظم المهمة ولا ينشئ سجلًا منافسًا.
- المهمة قد تكون مرتبطة بعملية/خدمة أو داخلية مستقلة.
- كل Service Job يملك مهمة Next Action واحدة نشطة على الأقل أثناء التنفيذ.

---

## 5. نظام الترقيم

| الكائن | النمط | مثال |
|---|---|---|
| Operation | `OP-{YYYY}-{NNNN}` | OP-2026-0041 |
| Service Job | `SRV-{YYYY}-{NNNN}` | SRV-2026-0085 |
| Factory Audit | `FA-{YYYY}-{NNNN}` | FA-2026-0021 |
| Quality Inspection | `QC-{YYYY}-{NNNN}` | QC-2026-0038 |
| Task | `TSK-{YYYY}-{NNNN}` | TSK-2026-0175 |
| Interaction | `INT-{YYYY}-{NNNN}` | INT-2026-0298 |
| Report | `RPT-{TYPE}-{YYYY}-{NNNN}-V{N}` | RPT-FA-2026-0021-V1 |
| Change Request | `CR-{YYYY}-{NNNN}` | CR-2026-0014 |

الترقيم آلي، متسلسل داخل Entity، غير قابل للتحرير بعد الإنشاء.

---

## 6. الأدوار

### 6.1 الأدوار الوظيفية

1. **System Administrator:** إعداد النظام والقواميس والصلاحيات، دون اعتماد تجاري تلقائي.
2. **Owner/CEO:** رؤية شاملة، اعتماد الاستثناءات والخصومات والنتائج الحساسة.
3. **Operations Manager:** توزيع العمليات والمهام، تغيير المسؤول، اعتماد الإغلاق.
4. **Account Manager:** العميل والتواصل والمتطلبات والعرض والمتابعة.
5. **Worker/Operator:** تنفيذ المهام المسموح بها وتحديث الأدلة والتقدم.
6. **Auditor/Inspector:** نماذج التدقيق وQC والأدلة الميدانية.
7. **Reviewer/Quality Supervisor:** مراجعة التقارير والنتائج وإعادتها أو اعتمادها.
8. **Finance:** الفواتير والمدفوعات والتخصيص والمصالحة، دون تغيير نتيجة التشغيل.
9. **Document Controller:** تصنيف ومراجعة ونشر المستندات.
10. **Customer External User:** بيانات شركته العامة فقط.

### 6.2 فصل الواجبات

- منشئ التقرير لا يعتمد تقريره النهائي إذا كانت الخدمة تتطلب مراجعة.
- منشئ حركة مالية لا يعتمدها عند تفعيل حد موافقة مالي.
- المفتش يسجل نتيجة مقترحة؛ المراجع يعتمد نتيجة النشر.
- إغلاق عملية بها رصيد أو استثناء مفتوح يحتاج مدير عمليات أو Owner.

---

## 7. رحلة المستخدم الأساسية

### 7.1 بدء عملية جديدة

زر موحد: **Start New Operation / بدء عملية جديدة**.

خطوات المعالج:

1. البحث عن العميل ومنع التكرار.
2. اختيار/إنشاء جهة اتصال.
3. تسجيل مصدر التواصل وملخص الطلب.
4. اختيار خدمة أو عدة خدمات.
5. إدخال الحد الأدنى لكل خدمة.
6. اختيار مالك الحساب ومدير العملية.
7. تحديد الأولوية والموعد المستهدف.
8. مراجعة الملخص.
9. الإنشاء كمسودة فقط.

لا ينشئ المعالج فاتورة أو دفعة أو طلب مورد أو اعتمادًا ماليًا تلقائيًا.

### 7.2 التحويل التجاري

```text
Draft Operation
→ Triage
→ Requirements Complete
→ Proposal Draft
→ Proposal Sent
→ Customer Accepted
→ Payment/Funding Gate (if required)
→ Ready for Execution
```

### 7.3 التنفيذ

- يفتح النظام Service Jobs المختارة.
- ينشئ المهام من القالب بعد تأكيد المدير.
- يعين المسؤولين.
- يظهر Next Action لكل خدمة وللعملية.
- تحفظ الأدلة والتحديثات في سياق الخدمة.

### 7.4 المراجعة والنشر

```text
Execution Complete
→ Draft Result/Report
→ Internal Review
→ Returned for Correction OR Approved
→ Ready to Publish
→ Published to Customer
→ Customer Decision/Acknowledgment
→ Complete
```

### 7.5 الإغلاق

لا تغلق العملية حتى تمر قواعد الإغلاق في القسم 20.

---

## 8. الحالات العامة للعملية

| الكود | الاسم | المعنى |
|---|---|---|
| draft | مسودة | غير جاهزة للتوزيع أو العرض للعميل |
| new | طلب جديد | تم تسجيل الحد الأدنى |
| triage | قيد الفرز | تحديد النطاق والخدمات |
| waiting_customer | بانتظار العميل | معلومات أو قرار من العميل |
| quotation | إعداد عرض | التسعير والنطاق قيد الإعداد |
| awaiting_acceptance | بانتظار الموافقة | العرض أرسل ولم يقبل بعد |
| awaiting_funding | بانتظار التمويل | بوابة مالية قبل التنفيذ |
| ready | جاهز للتنفيذ | المتطلبات والبوابة المالية مكتملة |
| in_progress | قيد التنفيذ | توجد خدمة نشطة |
| internal_review | مراجعة داخلية | نتيجة أو تقرير ينتظر الاعتماد |
| customer_action | بانتظار قرار العميل | قرار موثق مطلوب |
| on_hold | معلقة | سبب محدد وتاريخ مراجعة |
| completed | مكتملة | اجتازت الإغلاق |
| cancelled | ملغاة | بسبب مسجل وموافقة |
| archived | مؤرشفة | قراءة فقط بعد سياسة الأرشفة |

### 8.1 قواعد الانتقال

- لا انتقال من draft إلى new دون عميل، جهة اتصال، خدمة، مالك، وملخص.
- لا انتقال إلى quotation دون نطاق أولي لكل خدمة.
- لا انتقال إلى ready إذا كانت الخدمة تشترط قبول عرض أو دفعة ولم يتحققا.
- لا انتقال إلى internal_review دون اكتمال قائمة التنفيذ الإلزامية.
- لا انتقال إلى completed دون قواعد الإغلاق.
- on_hold وcancelled يحتاجان سببًا وفئة ومسؤول قرار وتاريخًا.
- إعادة فتح completed تحتاج صلاحية Manager وتسجل السبب.

---

## 9. دليل الخدمات Service Catalog

| الكود | الخدمة | نوع المسار |
|---|---|---|
| SOURCE_PRODUCT | بحث عن منتج | متخصص |
| SOURCE_SUPPLIER | بحث عن مورد | متخصص |
| VERIFY_SUPPLIER | التحقق من مورد | متخصص |
| NEGOTIATION | التفاوض مع المورد | بسيط |
| PURCHASE_MGMT | إدارة شراء | مرتبط بالدورة الحالية |
| FACTORY_AUDIT | تدقيق مصنع | متخصص |
| PRODUCT_QC | فحص جودة | متخصص |
| LOADING_INSPECTION | فحص تحميل | فرع من QC/Shipping |
| SHIPPING | إدارة شحن | ChinaPlanetLogistics |
| SHIPPING_QUOTE | عرض سعر شحن | بسيط ثم تحويل |
| DOCUMENTS_CUSTOMS | مستندات/تخليص | متخصص |
| CARGOX_NAFEZA | CargoX/Nafeza | متخصص |
| DHL_COURIER | DHL/مراسلات | بسيط |
| CUSTOM_SERVICE | خدمة مخصصة | قالب مدير |

كل عنصر في الدليل يحدد:

- الاسم العربي والإنجليزي.
- السعر/طريقة التسعير/العملة.
- الضريبة والحساب المحاسبي المعتمد.
- متطلبات البداية.
- هل يحتاج قبول عرض؟
- هل يحتاج دفعة أو تمويلًا قبل التنفيذ؟
- SLA.
- مراحل الخدمة.
- قالب المهام وقائمة التحقق.
- المخرجات وقالب التقرير.
- نوع المراجع المطلوب.

---

## 10. مسارات الخدمات

### 10.1 البحث عن منتج

```text
New Request → Requirements Review → Market Research → Candidate Comparison
→ Customer Shortlist → Samples/Quotation → Customer Selection → Complete
```

الحد الأدنى: المنتج، الاستخدام، المواصفات، الكمية، السعر المستهدف، البلد، الموعد، الصور المرجعية، متطلبات الشهادة والتغليف.

### 10.2 البحث/التحقق من مورد

```text
New → Requirements → Candidate Search → Initial Screening → Verification
→ Comparison → Customer Review → Selected/Rejected → Complete
```

### 10.3 إدارة الشراء

يعاد استخدام دورة Operations Center الحالية:

```text
Order Confirmed → Customer Funding → Supplier Deposit → Production Follow-up
→ Supplier Balance → QC/Release Decision → Ready to Ship → Shipment → Documents → Closed
```

### 10.4 الشحن

```text
Shipping Request → Rate/Route Review → Booking → Pickup/Loading → Departure
→ In Transit → Arrival → Customs → Delivery → Closed
```

تظل ETA ومهلة Free Time خطرين مستقلين.

### 10.5 المستندات

```text
Requirements Generated → Requested → Received → Under Review
→ Correction Required OR Approved → Published → Complete
```

---

## 11. تدقيق المصنع Factory Audit

### 11.1 أنواع الخدمة

- Online only.
- Offline/On-site only.
- Online + Offline.
- Document-only verification.
- Re-Audit.
- Custom Audit.

### 11.2 الحقول

- المصنع والمورد المرتبطان.
- الاسم الصيني والإنجليزي.
- العنوان والإحداثيات إن كانت معتمدة.
- جهة اتصال المصنع.
- رابط Alibaba/1688/Website.
- المنتج ونطاق التدقيق.
- سبب التدقيق وأسئلة العميل.
- المستندات والشهادات المطلوب التحقق منها.
- نوع الزيارة والتاريخ والمفتش.
- تعليمات الوصول والسفر.
- حالة تأكيد المصنع.
- مستوى السرية.
- السعر والعملة والإضافات المعتمدة.

### 11.3 سير العمل

```text
طلب جديد
→ جمع معلومات المصنع
→ تدقيق أونلاين
→ انتظار مستندات المصنع
→ تحديد موعد الزيارة
→ الزيارة الميدانية
→ رفع الأدلة
→ إعداد التقرير
→ مراجعة المشرف
→ جاهز للنشر
→ منشور للعميل
→ بانتظار قرار العميل
→ مكتمل
```

المراحل غير المناسبة لنوع الخدمة تتخطى بقواعد صريحة ولا تحذف من التاريخ.

### 11.4 أقسام التقرير

- الملخص التنفيذي.
- هوية المصنع والسجل القانوني.
- إثبات الموقع وأنه مصنع/شركة تجارية.
- المنشآت والمساحة والعمال.
- خطوط الإنتاج والآلات.
- القدرة الإنتاجية مع مصدر الرقم.
- المواد الخام والمخازن.
- نظام الجودة والاختبارات.
- الشهادات وحالة التحقق.
- خبرة التصدير.
- المخاطر والتناقضات.
- توصيات China Planet.
- الأدلة المرئية والملفات.
- النتيجة وحدود التقرير.

### 11.5 النتائج

- Recommended.
- Recommended with Conditions.
- Additional Verification Required.
- High Risk.
- Not Recommended.
- Insufficient Information.

مع درجة مخاطر: Low / Medium / High / Critical، وأسباب وإجراءات مقترحة.

### 11.6 بوابات الجودة

- لا يبدأ On-site دون عنوان، موعد، جهة اتصال، ومفتش.
- لا يكتمل الرفع دون تصنيف الأدلة الإلزامية.
- لا يرسل للمراجعة دون ملخص ومخاطر وتوصية.
- لا ينشر دون Reviewer Approval وقائمة الملفات العامة.

---

## 12. فحص الجودة Product QC

### 12.1 أنواع الفحص

- Pre-Production Inspection.
- During Production Inspection.
- Pre-Shipment Inspection.
- Production Completion Check.
- Container Loading Check.
- Sample Inspection.
- Reinspection.
- Custom Inspection.

### 12.2 البيانات العامة

- العميل والعملية والطلب.
- المصنع والمورد.
- نوع الفحص والموقع والتاريخ.
- المفتش وجهة اتصال المصنع.
- كمية الطلب ونسبة الإنتاج والكمية الجاهزة.
- الموعد المتوقع للشحن.
- المواصفة والعينة المرجعية.
- تعليمات وتحذيرات العميل.
- متطلبات العلامة والتغليف والملصقات.

### 12.3 خطوط المنتجات

كل فحص يحتوي منتجًا واحدًا أو أكثر، ولكل سطر:

- الاسم وSKU/Model.
- الصورة المرجعية.
- اللون والمقاس والنسخة.
- الكمية المطلوبة والجاهزة.
- حجم العينة.
- المواصفات والقياسات.
- التغليف والعلامات.
- تحذيرات العميل.
- نتيجة المنتج.

### 12.4 خطة AQL

- المعيار والإصدار.
- General Level I/II/III.
- Special Level عند الحاجة.
- Lot Size وSample Size.
- Critical/Major/Minor AQL.
- Accept/Reject limits.
- سبب وموافقة أي انحراف عن الخطة.

لا يحسب النظام قبولًا نهائيًا من أرقام حرة غير معتمدة؛ القيم من قاموس/خطة موقعة.

### 12.5 العيوب

- Critical.
- Major.
- Minor.
- Observation.
- Customer-approved Exception.

لكل عيب: المنتج، الوصف، الكمية، الموقع، الصور/الفيديو، التكرار، الإجراء التصحيحي، رد المصنع، حالة المعالجة.

### 12.6 سير العمل

```text
طلب فحص جديد
→ مراجعة متطلبات العميل
→ إعداد قائمة الفحص
→ تأكيد موعد المصنع
→ جاهز للفحص
→ المفتش في الموقع
→ الفحص جارٍ
→ رفع الصور والنتائج
→ إعداد التقرير
→ مراجعة المشرف
→ جاهز للنشر
→ منشور للعميل
→ بانتظار قرار العميل
→ إجراء تصحيحي أو إعادة فحص
→ مكتمل
```

### 12.7 النتائج

- Pass.
- Fail.
- Conditional Pass.
- Pending Customer Decision.
- On Hold.
- Reinspection Required.
- Incomplete.

قرار الشحن مستقل:

- Customer Approved Shipment.
- Repair Requested.
- Reinspection Requested.
- Shipment Rejected.
- No Decision Yet.

### 12.8 بوابات الجودة

- لا يبدأ الفحص دون خطوط منتجات وتعليمات وخطة فحص.
- لا يصدر Result دون Sample Size وDefect Summary.
- لا تعني Pass السماح الآلي بالشحن.
- لا ينشر التقرير دون Reviewer Approval.
- قرار العميل يسجل بالمستخدم والوقت والنص والنسخة التي راجعها.

---

## 13. سجل التواصل Interactions

### 13.1 القنوات

Phone، WhatsApp، WeChat، Email، Online Meeting، In-person Meeting، Website، Other.

### 13.2 الأنواع

New Inquiry، Quote Request، Follow-up، Approval، Rejection، Change Request، Complaint، Document، Decision، Other.

### 13.3 الحقول الإلزامية

- العميل وجهة الاتصال.
- التاريخ والوقت والمنطقة الزمنية.
- القناة والنوع.
- الملخص.
- القرار إن وجد.
- الموظف.
- العملية/الخدمة المرتبطة.
- الإجراء التالي والمسؤول والموعد.
- مستوى الظهور.
- موافقة/أساس الاحتفاظ بالتسجيل عند الحاجة.

### 13.4 المرفقات

Voice Note، Audio، Video، Image، Email File، Document، External Link.

الرابط الخارجي لا يكفي كأرشيف دائم؛ الملفات المهمة ترفع للنظام وفق السياسة.

---

## 14. مدير المهام وCPKanban

### 14.1 نوعا اللوحات

1. **Operations Board:** البطاقة تمثل Operation/Service Job والانتقال محكوم بالـworkflow.
2. **Tasks Board:** البطاقة تمثل Dolibarr Task والعمل اليومي.

### 14.2 حالات المهام

| الكود | الحالة |
|---|---|
| inbox | واردة |
| planned | مخططة |
| today | اليوم |
| in_progress | قيد التنفيذ |
| waiting_customer | بانتظار العميل |
| waiting_internal | بانتظار داخلي |
| blocked | متوقفة/عائق |
| review | بانتظار المراجعة |
| done | مكتملة |
| cancelled | ملغاة |

### 14.3 حقول المهمة

- المرجع والعنوان والوصف.
- النوع والأولوية.
- المنشئ والمسؤول والمراجع.
- تاريخ البداية والموعد.
- تقدير الجهد اختياريًا.
- Operation وService Job وProject والعميل والمورد والطلب والشحنة.
- Checklist.
- Dependencies.
- Blocked reason.
- Progress.
- Attachments/Comments.
- Visibility: internal افتراضيًا.
- Requires Review.

### 14.4 My Day

يرى الموظف:

- المتأخر.
- اليوم.
- الجديد المسند إليه.
- قيد التنفيذ.
- المعاد للتعديل.
- بانتظار رد.
- غدًا/قريبًا.
- ما أكمله اليوم.

### 14.5 صلاحيات الإسناد

- Worker: لنفسه، ولزميل فقط بصلاحية team_assign.
- Team Lead: داخل الفريق.
- Operations Manager: لأي موظف.
- تغيير مسؤول أو موعد مهمة حرجة يسجل السبب.

### 14.6 قواعد

- كل مهمة نشطة لها مسؤول واحد رئيسي؛ المشاركون Followers منفصلون.
- إكمال Checklist لا يغلق المهمة تلقائيًا إذا كانت تحتاج مراجعة.
- إكمال مهمة لا ينشر تقريرًا ولا يسجل دفعة ولا يغير قرار شحن.
- قوالب الخدمات تنشئ مهامًا بعد تأكيد Manager لمنع الإنشاء المكرر.
- retry أو trigger مكرر لا ينشئ المهمة نفسها مرتين؛ يستخدم template key/idempotency key.

---

## 15. الملفات والأدلة

### 15.1 التصنيفات

Product، Defect، Factory Exterior، Production Line، Machine، Warehouse، Packaging، Loading، Legal Document، Certificate، Communication، Report، Other.

### 15.2 دورة الملف

```text
Uploaded → Under Review → Approved OR Rejected → Published (optional) → Superseded/Archived
```

### 15.3 الحقول

- Owner customer/entity.
- Operation/Service/Task/Report section.
- النوع والتصنيف والوصف.
- Original name داخلي فقط وStored random name.
- MIME والحجم وchecksum.
- Caption والميلستون.
- Version/parent.
- Review status.
- Customer visible.
- Uploaded/reviewed/published by and timestamps.

### 15.4 الحماية

- تخزين خارج web root.
- فحص extension + MIME + content + size.
- منع traversal وsymlink escape.
- تنزيل عبر endpoint يتحقق من entity وfk_soc والصلاحية.
- تنظيف/عدم إظهار EXIF والموقع وأسماء المسارات.
- لا يظهر الملف للعميل إلا إذا Approved + Customer Visible + owned.

---

## 16. التقارير والإصدارات

### 16.1 الحالات

Draft، In Review، Correction Required، Approved، Ready to Publish، Published، Superseded، Withdrawn.

### 16.2 القواعد

- Report Sections بيانات منظمة، وليست ملف PDF فقط.
- PDF/HTML يولد من نسخة ثابتة من الأقسام والأدلة المعتمدة.
- Published immutable.
- التصحيح ينشئ Version جديدة ويربط السابقة.
- سبب التصحيح إلزامي.
- العميل يرى تاريخ النسخ العامة فقط.
- Withdrawal يحتاج Manager وسببًا؛ لا يحذف النسخة من سجل التدقيق.

---

## 17. بوابة العميل

### 17.1 ما يراه العميل

- عملياته وخدماته فقط.
- الحالة العامة والتقدم المسموح.
- آخر تحديث عام.
- Next Action والجهة المسؤولة.
- القرارات والمستندات المطلوبة منه.
- العروض/الطلبات/الفواتير المصرح بها.
- الرصيد والمدفوعات منفصلة بالعملة.
- الشحن وETA وFree Time والحالة الجمركية.
- تقارير Audit/QC المنشورة.
- الأدلة العامة المعتمدة.
- أسئلة وتأكيد اطلاع داخل العملية.

### 17.2 ما لا يراه

- تكلفة المورد والهامش.
- الملاحظات الداخلية.
- هوية الموظف غير المعتمدة للنشر.
- ملفات تحت المراجعة أو مرفوضة.
- مهام الفريق وتقييم الأداء.
- بيانات مصانع/موردين حساسة غير مصرح بها.
- أي عميل أو entity آخر.

### 17.3 أفعال العميل

- Acknowledge Update.
- Answer Question.
- Submit Requested Document فقط عند وجود Request نشط.
- Approve/Reject Decision ضمن نوع قرار واضح.
- Request Clarification.

Acknowledgment ليس موافقة مالية أو قبولًا تعاقديًا إلا إذا كان نوع القرار كذلك ومذكورًا صراحة.

---

## 18. لوحات العرض

### 18.1 المدير

- العمليات النشطة والمتأخرة.
- Exceptions بحسب الأثر والموعد.
- تقارير تنتظر الاعتماد.
- عمليات بلا مسؤول أو Next Action.
- عبء العمل حسب الموظف.
- المهام المتوقفة وأسبابها.
- SLA حسب الخدمة والجهة المتسببة.
- Audit risks وQC failures.
- الشحنات وETA/Free Time risk.
- المالية منفصلة بالعملة ودون خلط.

### 18.2 الموظف

- My Day.
- الإجراء التالي المقترح.
- مهامي المتأخرة والجديدة.
- قائمة المرحلة الحالية.
- التواصل المرتبط.
- الأدلة والنماذج المطلوبة.
- زر Start / Block / Send to Review / Complete.

### 18.3 العميل

- الإجراء المطلوب منه أولًا.
- خدماته النشطة.
- تقدم مبسط وصادق.
- التقارير الجديدة.
- الشحنات والفواتير والملفات العامة.
- دون Widgets داخلية أو مالية غير مخولة.

---

## 19. SLA والتنبيهات

### 19.1 حقول SLA

- Start event.
- Target duration.
- Working calendar/timezone.
- Warning threshold.
- Critical threshold.
- Pause reasons.
- Escalation owner.

### 19.2 أسباب الانتظار

Customer، Company، Factory/Supplier، External Authority، Force Majeure، Other.

### 19.3 تنبيهات المرحلة الأولى

داخل النظام فقط:

- مهمة جديدة/معادة.
- موعد اليوم/متأخر.
- تقرير ينتظر المراجعة.
- قرار عميل جديد.
- عملية بلا Next Action.
- Factory visit قريب وغير مؤكد.
- QC بلا تعليمات أو AQL.
- Shipment ETA/Free Time risk.
- مستند مطلوب مفقود.

Email/WhatsApp/WeChat/n8n خارج المرحلة الأولى ويحتاج اعتمادًا منفصلًا.

---

## 20. التغيير والتعليق والإلغاء والإغلاق

### 20.1 Change Request

يسجل:

- الطلب الأصلي والتغيير.
- السبب والطالب.
- الخدمات المتأثرة.
- أثر الوقت والسعر والعملات.
- المهام/المستندات الجديدة.
- موافقة العميل والمدير عند الحاجة.
- الحالة: Draft/Submitted/Approved/Rejected/Implemented.

### 20.2 On Hold

سبب وفئة ومسؤول وتاريخ مراجعة Next Review Date إلزامية. لا يسمح بتعليق مفتوح بلا موعد.

### 20.3 Cancellation

- السبب.
- من طلب الإلغاء.
- الأثر المالي.
- الأعمال المنفذة.
- مصير الملفات والتقارير.
- موافقة الصلاحية المناسبة.

### 20.4 قواعد الإغلاق

تغلق العملية فقط إذا:

- كل Service Jobs مكتملة أو ملغاة رسميًا.
- لا توجد مهام إلزامية مفتوحة/متوقفة.
- التقارير المطلوبة منشورة أو تم توثيق عدم الحاجة.
- قرارات العميل المطلوبة مسجلة.
- المستندات الإلزامية مكتملة.
- الموردون والشحنات وصلت لحالة نهائية مناسبة.
- فواتير Vision والتمويل والالتزامات مسواة أو الاستثناء المالي معتمد.
- يوجد Closing Summary داخلي وعام.
- يوجد Next relationship action اختياري مثل follow-up date.

---

## 21. القواميس

تدار من Admin ولا تكتب كقيم حرة:

- Service Types.
- Operation/Service/Task statuses.
- Priorities.
- Interaction channels/types.
- Waiting/hold/cancel reasons.
- Evidence/document types.
- Report results/risk levels.
- QC inspection/defect/AQL dictionaries.
- Factory Audit sections.
- Customer decision types.
- SLA templates.
- Teams/roles.

تعطيل القيمة يحفظ التاريخ ولا يغير السجلات السابقة.

---

## 22. التكامل المالي

- Proposal/Sales Order يعبر عن خدمات Vision والنطاق المتفق عليه.
- Supplier Orders/Invoices تبقى باسم المورد ومرتبطة بـProject/Operation.
- Customer Funding يظل في مصدره الحالي ولا يعاد نسخه داخل Operation.
- Operation يعرض Read Model وروابط وتلخيصًا لكل عملة.
- لا تنشأ حركة مالية من Task completion أو stage move.
- أي مبلغ يعرض معه amount/currency، وعند التحويل source/target/rate/date/source.
- تقارير الربحية لا تبدأ حتى تعتمد مصادر التكلفة والإيراد والحسابات من المحاسب.

---

## 23. المتطلبات غير الوظيفية

### 23.1 اللغات والوقت

- Arabic-first RTL وEnglish LTR.
- كل label في language files.
- وقت التخزين وفق Dolibarr/DB المعتمد، والعرض يوضح المنطقة الزمنية عند المواعيد الدولية.
- التواريخ الحرجة لا تعتمد على النص الحر.

### 23.2 الموبايل والميدان

- عرض 390px دون horizontal page scroll.
- Autosave draft للنماذج الطويلة.
- استعادة upload المنقطع أو إعادة المحاولة الآمنة.
- ضغط الصور/thumbnail دون تغيير الأصل قبل سياسة معتمدة.
- Offline الكامل مرحلة لاحقة.

### 23.3 الوصول

- Keyboard كامل.
- Focus واضح.
- Status نص + رمز وليس لونًا فقط.
- Contrast مناسب.
- Reduced motion.
- جداول تتحول إلى cards بالموبايل.

### 23.4 الأداء

- Pagination وقوائم مفلترة server-side.
- فهارس entity/fk_soc/status/assignee/due date/operation/service.
- Dashboard لا ينفذ استعلامات متكررة لكل صف.
- الصور تستخدم thumbnails وprotected streaming.

### 23.5 الاتساق والتزامن

- CSRF لكل mutation.
- optimistic/concurrency check بواسطة tms أو token.
- idempotency للـtriggers/templates/uploads/retries.
- لا يسمح لآخر حفظ صامت أن يمحو تعديل موظف آخر.

---

## 24. الأمان والتدقيق

- entity scope في كل جدول واستعلام.
- customer scope من session وليس URL فقط.
- صلاحيات Read/Create/Edit/Assign/Review/Publish/Cancel/Reopen/Admin منفصلة.
- سجل Audit append-only للأفعال الحساسة.
- Internal data لا يرسل أصلًا في HTML/JSON للعميل.
- الملفات محمية بمسار عشوائي وendpoint مصرح.
- CSRF، sanitize، escaping، MIME/content validation.
- لا أسرار أو credentials أو بيانات عملاء في Git أو logs.
- retention وlegal hold قبل أي cleanup تلقائي.

---

## 25. مراحل التنفيذ

### Phase 0 — حماية وتثبيت baseline

- جرد التغييرات المحلية الحالية.
- فصل عمل المستخدم عن التعديلات الجديدة.
- Backup DB/documents/custom.
- مطابقة versions/module IDs/schema.
- اعتماد migration strategy وrollback.

**بوابة القبول:** لا ملفات مجهولة، لا تعارض IDs، وrestore قابل للاختبار.

### Phase 1 — القواميس والصلاحيات والأساس

- Operation + Service Job schema.
- numbering.
- dictionaries.
- role/permission split.
- read-only operation list/card.

**القبول:** entity/role/customer isolation، create draft، no finance mutation.

### Phase 2 — Intake والتواصل

- Start New Operation wizard.
- duplicate customer check.
- interactions/attachments/next action.
- service selection and templates preview.

**القبول:** عملية متعددة الخدمات كمسودة، audit trail، لا فاتورة تلقائية.

### Phase 3 — Internal Tasks وKanban

- Dolibarr Task mapping.
- My Day وTeam Board وOperations Board.
- assignment/due/priority/block/review.
- checklist/comments/attachments.
- template idempotency.

**القبول:** manager/worker matrix، no duplicate tasks، customer cannot see internal tasks.

### Phase 4 — Factory Audit

- workflow/fields/checklists.
- mobile evidence.
- report builder/review/version/publish.
- portal projection.

**القبول:** Online/Offline/Hybrid، review gate، cross-customer/file tests.

### Phase 5 — Product QC

- inspection/product lines/AQL/defects/measurements.
- results and separate shipping decision.
- report/review/reinspection.
- portal projection.

**القبول:** multi-product، sample/defect calculations، no auto-shipping approval.

### Phase 6 — بقية الخدمات

- sourcing/supplier verification/purchase/shipping/documents templates.
- link current Operations Center and Logistics.
- common closure/change rules.

### Phase 7 — dashboards/alerts/training

- role dashboards.
- in-app alerts.
- SLA and exceptions.
- SOP help and synthetic training scenarios.

### Phase 8 — integrations later

- n8n/WhatsApp/WeChat/email/WooCommerce only after stable contracts and explicit approval.

---

## 26. الاختبارات الإلزامية

### 26.1 وظيفية

- new/existing customer.
- one/multiple services.
- acceptance/payment gates.
- task templates and retry.
- Audit all modes.
- QC multi-product and reinspection.
- report correction and versioning.
- hold/cancel/reopen/close.

### 26.2 صلاحيات

- Admin/Owner/Manager/Worker/Inspector/Reviewer/Finance/Customer.
- direct URL and forged IDs.
- list/detail/export/download/API.
- public/internal notes and files.

### 26.3 مالية

- USD/RMB/EGP separate.
- partial/overpayment and allocation.
- supplier vs Vision revenue.
- no task/workflow financial mutation.

### 26.4 ملفات

- invalid extension/MIME/content/size.
- traversal/symlink.
- rejected/unapproved/inactive versions.
- cross-customer access.

### 26.5 UX

- Arabic/English RTL/LTR.
- keyboard.
- 390px mobile.
- dark/light/high contrast if enabled.
- empty/partial/error states.
- autosave and concurrency conflict.

### 26.6 Upgrade/Rollback

- migration twice; second no-op.
- disable feature without data loss.
- restore snapshot.
- Dolibarr upgrade compatibility.

---

## 27. مؤشرات الأداء

- First response time.
- Time in each stage.
- SLA compliance.
- Delay owner/reason.
- Operations without next action.
- Overdue/blocked tasks.
- Review turnaround and correction rate.
- Audit risk distribution.
- QC pass/fail/reinspection by supplier/factory/product.
- On-time shipment/document readiness.
- Customer response/decision time.
- Service volume and revenue only from accountant-approved sources.

لا يستخدم عدد المهام وحده لتقييم الموظف؛ تختلف أوزان وتعقيدات المهام.

---

## 28. التدريب والتبني

- بيانات تدريب صناعية فقط.
- سيناريو كامل لكل خدمة.
- تعليمات داخل الشاشة.
- Checklist لأول أسبوع.
- SOP قصير لكل دور.
- Manager reviews adoption أسبوعيًا أول شهر.
- لا يسمح بالعمل الموازي الدائم في Trello والنظام بعد تاريخ الانتقال، مع فترة انتقال موثقة فقط.

---

## 29. خارج النطاق الأول

- إرسال WhatsApp/WeChat أو Email فعلي.
- ذكاء اصطناعي يقرر Pass/Fail أو توصية المصنع.
- تتبع GPS حي.
- Offline كامل.
- WooCommerce ثنائي الاتجاه.
- جمع العملات أو حساب الربحية دون اعتماد محاسبي.
- حذف تلقائي للأدلة.
- تطبيق موبايل Native.

---

## 30. القرارات الافتراضية المقترحة للاعتماد

1. Operation واحدة تستطيع احتواء خدمات متعددة: **نعم**.
2. Project يبقى الحاوية التجارية: **نعم**.
3. Dolibarr Tasks مصدر المهام وCPKanban واجهة: **نعم**.
4. المهام Internal افتراضيًا: **نعم**.
5. التقارير تحتاج Reviewer قبل النشر: **نعم**.
6. نتيجة QC منفصلة عن قرار الشحن: **نعم**.
7. Worker يستطيع إسناد نفسه؛ إسناد الآخرين بصلاحية: **نعم**.
8. التنبيهات في النظام فقط في المرحلة الأولى: **نعم**.
9. Audit/QC أول خدمتين متخصصتين بعد الأساس والمهام: **نعم**.
10. تنفيذ تدريجي feature flags ودون core edits: **نعم**.

---

## 31. مخاطر التنفيذ

| الخطر | المعالجة |
|---|---|
| ازدواج Project/Operation/Card/Task | تعريف مصدر السلطة وروابط فريدة وتقارير مصالحة |
| كسر التعديلات الحالية | Phase 0 وجرد/backup/branch نظيف |
| تسريب معلومات داخلية | فصل الحقول + server-side scope + اختبارات forged IDs |
| تعقيد واجهة الموظف | My Day وNext Action وprogressive disclosure |
| مهام مكررة من triggers | idempotency keys وunique constraints |
| نشر تقرير غير مكتمل | review/publish permission gates |
| ربط Pass بالشحن تلقائيًا | قرار شحن مستقل |
| تضخم الملفات | limits/thumbnails/retention/storage monitoring |
| خلط العملات | buckets مستقلة ومصدر مالي واحد |
| فشل التبني | تدريب وقوالب ومنع العمل الموازي بعد الانتقال |

---

## 32. معيار اكتمال البرنامج

يعتبر النظام التشغيلي مكتملًا عندما يستطيع مستخدم مخول تنفيذ السيناريو التالي دون سجل خارجي:

1. تسجيل العميل والتواصل.
2. إنشاء عملية متعددة الخدمات.
3. إعداد العرض وتأكيده.
4. توزيع المهام ومتابعتها.
5. إدارة الشراء والتمويل والموردين عند الحاجة.
6. تنفيذ Audit وQC ونشر التقارير.
7. إنشاء الشحنة والمستندات.
8. عرض معلومات آمنة في بوابة العميل.
9. تسجيل قرارات العميل والتغييرات.
10. إغلاق العملية بقواعد موثقة.
11. استخراج سجل كامل يوضح من فعل ماذا ومتى.

---

## 33. الموافقة

قبل بدء Phase 1 البرمجية يجب اعتماد:

- نموذج Operation/Service Job/Task.
- القواميس والحالات.
- مصفوفة الصلاحيات.
- Service Catalog الأولي والأسعار/العملات.
- Audit/QC checklists والنتائج.
- سياسة الملفات والاحتفاظ.
- قواعد التقرير والنشر.
- قواعد الإغلاق والاستثناءات.

بعد الاعتماد تتحول هذه الوثيقة إلى baseline؛ أي تغيير وظيفي بعدها يسجل كـChange Request للوثيقة والتنفيذ.
