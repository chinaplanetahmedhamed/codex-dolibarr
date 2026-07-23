# تقرير إنجاز — M036

## النتيجة

`PASS / ARCHITECT_ACCEPTED` — أُنجز الربط المحلي لسجل طلبات الشحن ولوحات العدادات. أصبحت حجوزات العميل تظهر كسجل تشغيلي مستقل عن طلبات البيع، تحت العميل الصحيح، وفي لوحة الإدارة ومركز الشحن، مع حالات متبادلة الاستبعاد ودليل سعر مقبول محمي. اجتازت Shipping Operations وSecurity وUI/UX وQA دون ملاحظات تصحيحية متبقية.

## المصدر والبيئة

- المستودع: `git-work/codex-dolibarr` فقط.
- البيئة: Dolibarr 23.0.3 المحلي على `http://127.0.0.1:8088`.
- نطاق التنفيذ: تطوير وruntime محليان فقط.
- Git والنشر: لا commit أو push أو `backup-erp` أو staging أو production.
- حالة الشجرة قبل M036: كانت تحتوي تعديلات ومهام سابقة؛ حُفظت ولم تُحذف أو تُستبدل.

## النتيجة التشغيلية

- شاشة داخلية جديدة: `Shipping Orders / طلبات الشحن` مع بحث بالمرجع/العميل/الشحنة/الإجراء التالي، تصفية عميل، وحالات قابلة للنقر.
- كل سجل يعرض مرجع ChinaPlanet، مصدر الطلب، العميل، المسار والمعدات والعدد والإجمالي والعملة المقبولة، Carrier SO/booking، الحالة، التاريخ، الإجراء التالي، والشحنة المرتبطة إن وُجدت.
- الحجز اليدوي/القديم يبقى ظاهرًا، لكن دليل السعر غير الموجود يظهر `Not captured` ولا يُختلق من بيانات قابلة للتعديل.
- لوحة الإدارة تعرض: Total Current وActive وConfirmed/SO Received وClosed وCancelled، مع بقاء مؤشرات Sales Orders الأصلية منفصلة.
- مركز العميل المحدد يعرض البطاقة والطلبات الحديثة داخل الإدارة فقط. المستخدم الخارجي لا يتلقى بيانات أو روابط M036.
- مركز الشحن يحتوي رابطًا جديدًا لا يظهر إلا لمن لديه صلاحية قراءة Operations.

## الملفات المتأثرة

- `custom/chinaplanetoperations/class/OperationsService.class.php`
- `custom/chinaplanetoperations/operations/shipping_orders.php` (جديد)
- `custom/chinaplanetoperations/css/shipping_orders.css` (جديد)
- `custom/chinaplanetlogistics/index.php`
- `custom/chinaplanetcustomercenter/board.php`
- `custom/chinaplanetcustomercenter/css/customer_dashboard.css`
- `custom/chinaplanetcustomercenter/admin_dashboard.php`
- `custom/chinaplanetcustomercenter/css/admin_dashboard.css`
- `custom/chinaplanetcustomercenter/ajax/dashboard_layout.php`
- `deploy/tests/test_m036_shipping_orders_source.php` (جديد)
- `deploy/tests/test_m036_shipping_orders_runtime.php` (جديد)
- `deploy/tests/m036_browser_fixture.php` (جديد؛ بيانات متصفح محلية مؤقتة قابلة للتنظيف الدقيق)
- `deploy/tests/m036_dashboard_layout_backup.php` (جديد؛ حفظ/استعادة تخطيط الإدارة المحلي أثناء اختبار التخصيص)
- `deploy/tests/test_unified_admin_shell.php`
- `docs/MISSIONS/M036_SHIPPING_ORDERS_ADMIN_INTEGRATION_AND_DASHBOARD_METRICS.md`
- `docs/REPORTS/M036_COMPLETION_REPORT.md` (جديد)
- `docs/CHANGELOG.md`
- `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md`

## قاعدة البيانات والإعداد والمحاسبة

- مخطط قاعدة البيانات: لا تغيير.
- بيانات الأعمال: لا تغيير دائم من M036. اختبار runtime استخدم transaction ثم rollback نظيفًا، وبيانات المتصفح المؤقتة حُذفت وتحقق `rows=0` بعد الاختبار.
- الصلاحيات: لا صلاحيات جديدة ولا توسيع لمنح موجودة؛ القراءة تعتمد Operations read والإنشاء/التعديل يبقى Operations write.
- إعدادات الوحدات: لا تفعيل أو تعطيل.
- المحاسبة: لا إنشاء quotation أو sales order أو invoice أو payment أو قيد أو إيراد.

## الأمان وظهور العميل

- كل استعلام يفرض `o.entity` والصفوف الحالية `status=1` ونوع `shipping_booking`.
- ربط العميل والحجز وبيانات العملية والشحنة يفرض الكيان، وربط الشحنة يفرض العميل نفسه.
- مركز العميل المحدد يفرض `entity + fk_soc` ويستبعد booking مختلف الملكية.
- سجل الإدارة يستخدم projection allowlist ولا يعرض raw JSON أو hashes أو forwarder أو تكلفة مورد أو هامش أو ملاحظات/ملفات خاصة.
- اختلاف ملكية booking أو shipment يُعلّم كاستثناء داخلي؛ دليل السعر/الشحنة غير المتطابق لا يُعرض.
- جلسة عميل خارجية فعلية تلقت `Access denied` عند فتح المسار الإداري مباشرة.

## تعريف الحالات

- `Cancelled`: مرحلة `cancelled`.
- `Closed`: مرحلة `completed`.
- `Confirmed / SO Received`: مرحلة `published` مع booking status مقبول/مؤكد ومع Carrier SO أو carrier booking number.
- `Active`: كل سجل حالي غير نهائي آخر، بما فيه `published` ناقص دليل التأكيد.
- `Total Current`: مجموع الحالات الأربع؛ الاختبار أثبت المصالحة الحسابية.
- لا تُستخدم `Succeeded` لأنها لا تعني اكتمال العملية.

## التحقق

`PASS` بعد المراجعة النهائية:

- PHP lint: PASS لكل ملفات PHP المتأثرة واختبارات M036.
- `test_m036_shipping_orders_source.php`: PASS للصلاحية وallowlist والحالات والـdashboard allowlist والاستجابة.
- `test_m036_shipping_orders_runtime.php`: PASS؛ `total=7`, `active=4`, `confirmed=1`, `closed=1`, `cancelled=1`, ownership isolated، foreign entity excluded، rollback clean.
- M035 source + runtime regression: PASS؛ idempotency والملكية والسعر الآمن والمحاسبة unchanged.
- Operations workflow + multilingual SOP: PASS.
- Customer portal scope integration: PASS؛ tampered `socid` ignored وcross-customer data denied.
- Unified admin shell، M033، M032، وadmin accessibility: PASS.
- Browser admin: سجل الطلبات، لوحة الإدارة، مركز الشحن، ومركز العميل المحدد فُتحت دون fatal/PHP warning.
- Browser external: direct admin route = `Access denied`.
- Browser fixtures: سبعة طلبات فعلية مؤقتة في Dolibarr المحلي غطت الحالات الأربع، شحنة مرتبطة، اختلاف ملكية booking، واختلاف ملكية shipment؛ المصالحة العامة `7 = 4 + 1 + 1 + 1`.
- Filter reconciliation: العميل المحدد أعاد `5 = 3 + 1 + 1 + 0` مع حفظ `socid` و`q` داخل روابط KPI؛ البحث الدقيق عن الطلب المؤكد أعاد صفًا واحدًا وKPI مؤكدًا واحدًا.
- Responsive browser checks: 1440/1024/768/390/320، بلا overflow أفقي. KPI يتدرج 5/3/2/1، والنتائج تتحول من table إلى semantic cards تحت 1100px، وتعرض البطاقة تحذير اختلاف ملكية الشحنة مثل صف سطح المكتب.
- Text zoom: تكبير Chrome الفعلي إلى 200% أعطى `innerWidth=864`, `overflow=0`, سبع بطاقات ظاهرة، العدادات الخمسة والتحذير وضوابط ملائمة؛ أُعيد Chrome إلى إعداد المستخدم الأصلي 110%.
- Dashboard customization: حُفظ تخطيط قديم من 12 صفًا لا يحتوي `shipping_orders`، ثم صُغّر الودجت وأُخفي وحُفظ من الواجهة؛ ثبّت reload التغيير، وأعاد Reset Default الودجت بالحجم الكامل، ثم استُعيد التخطيط الأصلي حرفيًا وتحقق `backup=no`.
- RTL/LTR: مراجع ومسارات وأسعار معزولة بـ`bdi dir=ltr` والنصوص المتغيرة تستخدم `dir=auto`.
- Empty state: ظهر ثنائي اللغة بعد تنظيف fixtures، مع عدادات صفرية وعدم وجود overflow.
- Fixture cleanup: `M036_BROWSER_FIXTURE=STATUS rows=0`; تخطيط الإدارة الأصلي `rows=12 backup=no`.
- Source/runtime parity: ملفات التطبيق التسعة المتأثرة تطابقت byte-for-byte بين المستودع canonical وDolibarr runtime المحلي.
- `git diff --check`: PASS.

## حالة التطابق

- Admin ERP: `RUNTIME_VERIFIED_LOCAL`.
- Customer Portal: `UNCHANGED_VERIFIED`; مسار الإدارة مرفوض للعميل الخارجي.
- Mobile App: `PENDING`; يلزم Mission مستقلة لعقد API وشاشة تاريخ حجز آمنة.

## المخاطر والمتابعة

- التحقق استخدم بيانات متصفح محلية مؤقتة فقط ثم حذفها بدقة؛ لا توجد صفوف M036 تجريبية متبقية.
- لا يوجد تنفيذ هاتف أو API ضمن M036.
- لا يوجد قياس performance على حجم إنتاجي؛ الحد الأقصى الحالي 500 صف والاستعلامات تستخدم العلاقات الفريدة الموجودة.
- يبقى إنشاء Dolibarr Sales Order قرارًا منفصلًا وليس أثرًا تلقائيًا للحجز.

## التراجع

استرجاع ملفات التطبيق المذكورة، وحذف ملفي الشاشة/CSS واختبارات/مساعدات M036 الجديدة، ثم استرجاع وثائق M036. لا توجد migration أو بيانات أو إعدادات أو قيود محاسبية تحتاج إلى rollback.

## المراجعات

- Shipping Operations: `PASS`؛ مصدر الحقيقة، دليل السعر الثابت، تمييز المراجع، الحالات المتبادلة الاستبعاد، الملكية والمصالحة التشغيلية محققة.
- Security: `PASS`؛ الإذن قبل الاستعلام، entity/fk_soc، allowlist، إخفاء اختلاف الملكية، العزل ورفض المسار الخارجي محققة.
- UI/UX: `PASS`؛ عدادات مفلترة، بطاقات متجاوبة، RTL/LTR، تحذيرات الملكية، 320–1440px وChrome zoom 200% دون overflow.
- QA: `PASS`؛ الصفوف الحقيقية المؤقتة، الحالات الأربع، التخصيص Save/Reload/Reset، التنظيف، regressions و`git diff --check` محققة.
- Architect: `ARCHITECT_ACCEPTED` محليًا؛ لا Git أو staging أو production ضمن هذا القبول.

## تصحيح مزامنة محلي بعد القبول — 2026-07-23

- أظهر الرابط العلوي المحفوظ في Dolibarr خطأ 404 لأن ملف التوافق `custom/chinaplanetcustomercenter/index.php` كان موجودًا في المصدر canonical لكنه لم يُنسخ إلى runtime المحلي أثناء المزامنة الجزئية.
- نُسخ الملف نفسه byte-for-byte إلى Dolibarr 23.0.3 المحلي دون تعديل المصدر أو قاعدة البيانات أو الصلاحيات.
- أُعيد فتح الرابط الأصلي كاملًا من المتصفح؛ تحوّل إلى `/custom/chinaplanetcustomercenter/board.php` وظهر Customer Center الفعلي بلا 404 وبلا overflow أفقي.
- كشف فحص بقية شريط الإدارة أن المزامنة الجزئية نفسها أغفلت صفحات إضافية من Customer Center وLogistics؛ لذلك كان `dol_buildpath()` يعيد مسارات بلا `/custom` فتؤدي إلى 404.
- أُضيفت الملفات المفقودة فقط من وحدتي `chinaplanetcustomercenter` و`chinaplanetlogistics` إلى runtime المحلي باستخدام مزامنة بلا حذف وبلا استبدال للملفات الموجودة. لم تُطبّق ملفات SQL ولم تتغير قاعدة البيانات أو إعدادات الوحدات.
- بعد إعادة تحميل Dashboard ظهرت كل الروابط بمسار `/custom/...`. فُتحت فعليًا بجلسة Admin صفحات Documents، Reports، SOP Library، Operations، Financial Center، وNew Shipment دون 404 أو PHP warning/fatal، ثم أُعيد Chrome إلى Dashboard.
