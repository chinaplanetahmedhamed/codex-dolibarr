# تقرير إنجاز — M034

## النتيجة

`PASS` — تم تصحيح حالة نشر أسعار الشحن محليًا. رفع ملف مطابق يعيد استخدام الدفعة الموجودة مع شرح حالتها الفعلية، وتظهر الدفعات الآن بوضوح كمسودة أو منشورة وحالية أو سجل منشور. لا يمكن إعادة تنشيط دفعة حالية أو سجل أقدم بطلب مباشر.

## المصدر والبيئة

- المستودع: `git-work/codex-dolibarr`.
- الفرع: `integration/current-source`؛ نقطة البداية `c737bb0`.
- البيئة: Dolibarr المحلي على `http://127.0.0.1:8088`.
- Runtime commit: `aa6e56ca5faf197cc92c79bd4cb5c45df88a7bd1`.
- النشر عند إغلاق التنفيذ المحلي: لم يوجد Git push أو `backup-erp` أو ERP حي؛ أضيف اعتماد إنتاج منفصل لاحقًا في 2026-07-23.

## الملفات المتأثرة

- `custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php`
- `custom/chinaplanetshippingprices/admin/import.php`
- `custom/chinaplanetshippingprices/admin/batch.php`
- `deploy/tests/test_m034_shipping_price_publication_state.php`
- `deploy/tests/test_m034_shipping_price_publication_runtime.php`
- `docs/MISSIONS/M034_SHIPPING_PRICE_PUBLICATION_STATE_CORRECTION.md`
- `docs/REPORTS/M034_COMPLETION_REPORT.md`
- `docs/CHANGELOG.md`
- `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md`

## البيانات والصلاحيات

- مخطط قاعدة البيانات: لا تغيير.
- بيانات الأسعار: لا تعديل من اختبارات المهمة؛ اختبار التشغيل قراءة فقط.
- النشر وإعادة التفعيل: POST مع CSRF وبصلاحية الاستيراد الداخلية الحالية.
- العميل الخارجي: لا تغيير ولا كشف لتكاليف المورد أو أدوات الإدارة.

## التحقق

`PASS`

- PHP lint: PASS لملفات التطبيق والاختبارين الجديدين.
- اختبار عقد M034 للمصدر: PASS.
- اختبار التكامل التشغيلي المحلي: PASS؛ الدفعة 33 تحتوي 607 سعرًا، منها 607 حالية و523 متاحة في بحث العميل.
- اختبارات وحدة أسعار الشحن الحالية: PASS.
- اختبارات CSRF وحدود رفع ملفات الأسعار: PASS.
- اختبار التكامل الحالي لأسعار الشحن: PASS؛ `rates=15914`, `minimum=1000`, `sokhna_results=9`, `history_points=368`.
- تطابق المصدر مع ملفات runtime المحلية: PASS.
- فحص سطح المكتب: PASS لصفحتي الاستيراد ومراجعة الدفعة؛ الحالات والأعداد صحيحة ولا توجد تحذيرات PHP أو overflow.
- فحص الهاتف عند 390×844: PASS لصفحتي الإدارة؛ البطاقات والأزرار والحالات تتكيف دون overflow مادي.
- `git diff --check`: PASS.

## حالة التطابق

- Admin ERP: `VERIFIED_LOCAL`.
- Customer Portal: `NOT_APPLICABLE`؛ لا تغيير في عقد أو شاشة العميل.
- Mobile App: `NOT_APPLICABLE`؛ لا تغيير في API أو البيانات أو التصميم الظاهر للعميل.

## التراجع

استرجاع ملفات التطبيق الثلاثة المذكورة إلى نسخها السابقة فقط، ثم مزامنتها إلى runtime المحلي. لا توجد قاعدة بيانات أو بيانات تحتاج إلى تراجع.

## المخاطر والمتابعة

- لم تُختبر عملية نشر فعلية جديدة لأن معيار المهمة يمنع تعديل بيانات الأسعار أثناء التحقق؛ تم اختبار الحماية والعقد واستخدام حالة البيانات الحالية قراءة فقط.
- قبل ملحق الإنتاج لم يوجد push أو نشر إلى `backup-erp` أو ERP الحي؛ نتيجة الإصدار المباشر المعتمد ستُسجل أدناه.

## ملحق إصدار الإنتاج — 2026-07-23

- أحمد أعطى أمرًا مباشرًا بنشر M034 إلى ERP الحي.
- Runtime commit: `aa6e56ca5faf197cc92c79bd4cb5c45df88a7bd1`.
- Artifact v3 SHA-256: `e5149add03c1e45685ee6eef8bf33af81ec2fc161a95f5e1d4a8d4a1c05a133e`.
- Allowlist: ملفات التطبيق الثلاثة المذكورة في تقرير الإنجاز فقط.
- الحالة الحالية: `AWAITING_PRODUCTION_DEPLOYMENT`؛ ستُسجل نتيجة النسخ الاحتياطي والتطبيق والتحقق والتراجع بعد التنفيذ.
- إعادة التحقق في 2026-07-23: تطابق محتوى الحزمة مع الـRuntime commit واختبار المصدر وPHP lint نجح؛ اختبار قاعدة البيانات المحلي للقراءة فقط تعذر لأن نقطة MariaDB المحلية لم تكن متاحة، لذلك يلزم نجاح preflight والتحقق الحي قبل اعتبار الإصدار مقبولًا.
- محاولة v2: فشلت compare بصورة آمنة قبل أي تغيير بسبب Apple extended metadata وفحص ثابت قديم؛ v2 مجمدة وغير منشورة، وv3 مبنية من نفس Runtime commit دون xattrs وبفحص ثابت صحيح.
