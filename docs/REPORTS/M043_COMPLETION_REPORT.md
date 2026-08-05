# تقرير تنفيذ محلي — M043

## النتيجة

`STAGING_VERIFIED` — اكتمل تنفيذ وربط React بـDolibarr للاستفسارات وطلبات الكتالوج وبحث أسعار FCL وطلبات حجز الحاويات، ثم نُشر الإصدار المقيد `M043-C01` على `backup-erp` فقط. نجحت عقود الأمان والاختبارات المحلية، كما نجحت مراحل staging `preflight/apply/verify` وقبول شاشة موظف Dolibarr الفعلية، بما فيها زر `Website Requests / طلبات الموقع` من لوحة الإدارة وعرض 390px. لم يُستخدم n8n ولم تُلمس production.

## حدود التنفيذ

- Dolibarr worktree: `feature/m043-public-website-intake`.
- React worktree: `feature/m043-dolibarr-public-gateway`.
- بدأ التنفيذ والقبول الوظيفي محليًا بتاريخ 2026-08-05، ثم وافق أحمد على staging ونُشر الإصدار `M043-C01` على `backup-erp` بتاريخ 2026-08-06.
- لم يُستخدم n8n، ولم يحدث أي deploy أو تغيير على production.
- لم تُنشأ Third Party أو Proposal أو Sales Order أو Invoice أو Shipment أو Payment أو حركة مخزون أو قيد محاسبي تلقائيًا.

## ما تم تنفيذه

- بحث عام مقيد بأسعار FCL المنشورة عبر صلاحية `website_rate_search`، بمدخلات exact `POL + POD + container_type` وحد 20 نتيجة.
- قوائم مترابطة في React بترتيب دولة الوصول ثم POL ثم POD؛ مصدرها دليل مسارات محدود من أسعار Dolibarr المنشورة والمؤهلة فقط، ولا يعرض أسعارًا أو معرّفات داخلية أو خطوطًا ملاحية أو أعداد توفر.
- رسم مرجعي لاتجاه السعر خلال 12 شهرًا لنفس المسار والحاوية، يعتمد متوسط `sale_price` العام المنشور شهريًا ويعرض التاريخ والسعر والعملة فقط؛ لا يعرض cost أو margin أو carrier أو rate identifiers، وموسوم بوضوح كمرجع تاريخي غير ملزم.
- DTO عام allowlist لا يعرض `rate_id` أو fingerprint أو cost أو margin أو بيانات المصدر، مع رمز AES-256-GCM صالح عشر دقائق.
- إعادة جلب السعر وإعادة تسعيره عند الإرسال مع مقارنة fingerprint ثابتة الزمن وتخزين لقطة تجارية غير قابلة للتعديل.
- جدول استقبال عام entity-scoped يفصل بيانات الاتصال والأطراف عن JSON الطلب واللقطة التجارية، مع مفتاح idempotency فريد وHMAC.
- صلاحيات منفصلة لمستخدم API وموظف القراءة والتحديث والترقية.
- قائمة موظف لتصنيف الطلب وربطه بطرف ثالث موجود في الكيان نفسه وتحويله يدويًا إلى Operations واحدة فقط.
- الشحنة العادية تتحول إلى `shipping_booking`، والمقيدة/DG تتحول إلى `shipping_inquiry`، وكل عملية تبدأ `customer_visible=0`.
- روتين anonymization قابل لإعادة التشغيل بعد 180 يومًا للطلبات المغلقة/المرفوضة/المحولة.
- بوابة Node ثابتة المضيف، HTTPS خارج loopback، بدون redirects حاملة للاعتماد، بمهلة وحجم استجابة محدودين ومفتاح Dolibarr server-only.
- مسار B2B يستخدم WordPress لحساب packing plan فقط، ثم يحفظ الطلب في Dolibarr ولا يستدعي WordPress `/request` لإنشاء WooCommerce order.
- مسار React عام `/shipping-rates` بالعربية والإنجليزية والفرنسية مع 20GP/40GP/40HQ ورسالة صريحة بأن الإرسال طلب مراجعة لا تأكيد مساحة.

## الملفات المتأثرة — Dolibarr

- `custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php`
- `custom/chinaplanetshippingprices/class/ShippingPriceService.class.php`
- `custom/chinaplanetshippingprices/api/class/api_chinaplanetshippingprices.class.php`
- `custom/chinaplanetshippingprices/core/modules/modChinaPlanetShippingPrices.class.php`
- `custom/chinaplanetoperations/class/PublicWebsiteRequestService.class.php`
- `custom/chinaplanetoperations/api/class/api_chinaplanetoperations.class.php`
- `custom/chinaplanetoperations/operations/public_requests.php`
- `custom/chinaplanetoperations/sql/llx_cpo_public_request.sql`
- `custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php`
- `custom/chinaplanetcustomercenter/admin_dashboard.php`
- `custom/chinaplanetcustomercenter/lib/admin_shell.lib.php`
- `custom/chinaplanetoperations/langs/{ar_SA,en_US,zh_CN}/chinaplanetoperations.lang`
- `deploy/tests/test_m043_public_website_intake_source.php`
- `deploy/tests/test_m043_public_rate_token.php`
- `deploy/tests/test_m043_public_website_intake_runtime.php`
- `docs/CHANGELOG.md`
- `docs/REPORTS/M043_COMPLETION_REPORT.md`
- `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md`

## الملفات المتأثرة — Moaz React/Node

- `.env.example`, `README.md`, `package.json`
- `server/config.js`, `server/dolibarr.js`, `server/shipping.js`, `server/leads.js`, `server/b2b.js`, `server/http.js`
- `server/{dolibarr,shipping,leads,b2b,http}.test.js`
- `src/App.jsx`, `src/components/Navbar.jsx`, `src/i18n/translations.js`, `src/index.css`, `src/lib/api.js`
- `src/pages/B2BPage.jsx`, `src/pages/ShippingRatesPage.jsx`, `src/pages/ShippingRatesPage.test.js`

## قاعدة البيانات والصلاحيات والإعداد

- جدول إضافي واحد: `llx_cpo_public_request`، دون تعديل جداول الأعمال الموجودة.
- أنشئ اختبار runtime الجدول محليًا وتركه فارغًا؛ حذف سعرَي/طلبات الاختبار الاصطناعية بعد النجاح.
- صلاحية الأسعار: `website_rate_search` (`500412`).
- صلاحية API للاستقبال: `website_intake_create` (`500511`).
- صلاحيات الموظف: `publicrequest_read/update/promote` (`500512–500514`).
- أسرار Dolibarr المطلوبة: `CHINAPLANET_PUBLIC_RATE_TOKEN_KEY` و`CHINAPLANET_PUBLIC_REQUEST_HMAC_KEY`، كل منهما 32 محرفًا على الأقل.
- إعداد Node المطلوب: `DOLIBARR_API_BASE_URL`, `DOLIBARR_ALLOWED_HOST`, `DOLIBARR_API_KEY`, `PUBLIC_SITE_ORIGIN`, `TRUSTED_PROXY_HOPS`.
- لا يوضع أي سر في `VITE_*`.

## التحقق المنفذ

- PHP lint لكل ملفات M043: PASS.
- `test_m043_public_website_intake_source.php`: PASS.
- `test_m043_public_rate_token.php`: PASS للتشفير والعبث والانتهاء والـallowlist.
- `test_m043_public_website_intake_runtime.php`: PASS على MariaDB المحلية؛ quote، booking، duplicate same-reference، changed-payload 409، snapshot total، و`space_confirmation=false`؛ cleanup ناجح.
- Node tests: `42/42 PASS` بعد إضافة عقد دليل المسارات واتجاه السعر والتحقق من ISO-2، مع بقاء عدم إنشاء WordPress order.
- UI contract tests: `5/5 PASS`، ومنها إثبات استبدال حقلي POL/POD النصيين بقوائم مترابطة ووجود الرسم المرجعي الآمن.
- `npm run lint`: PASS.
- `npm run build -- --configLoader runner`: PASS.
- متصفح محلي بخدمة Dolibarr وهمية: البحث NANSHA→ALEXANDRIA، ظهور `2450.5 USD / 40HQ`، إرسال الطلب والحصول على `WEB-LOCAL-UI-001`: PASS.
- responsive: 430×932 و390×844، overflow غير موجب، RTL العربية وLTR الإنجليزية/الفرنسية: PASS.
- تبديل اللغة بعد النجاح حفظ POL والنموذج والمرجع وغيّر رسالة النجاح إلى اللغة الحالية: PASS بعد تصحيح مكتشف أثناء الاختبار.
- console warnings/errors في مسار القبول: لا يوجد.
- `git diff --check`: PASS.
- فحص source الخاص بزر لوحة الإدارة: PASS للوجهتين المشتركتين في navigation وQuick Actions.

### إعادة تحقق تحسين القوائم — 2026-08-05

- PHP lint وعقد المصدر واختبار token/options النقي: PASS.
- build وlint واختبارات Node/UI: PASS.
- المتصفح المحلي: مصر → NANSHA → ALEXANDRIA → 40HQ أعاد `2450.5 USD` دون أخطاء console.
- الرسم المحلي لنفس المسار عرض 12 نقطة من سبتمبر 2025 إلى أغسطس 2026، والأحدث `2450.5 USD`، والأقل `2290 USD`، والأعلى `2780 USD`، دون أخطاء console.
- تعذر تكرار اختبار DB الاصطناعي في هذه الجولة لأن MariaDB المحلية على `127.0.0.1:8889` لم تكن متصلة؛ لا يغيّر ذلك نجاح الجولة السابقة المسجل أعلاه، لكنه يبقي إعادة تحقق DB لهذا التحسين معلقة حتى تشغيل MariaDB.

## حدود القبول الحالية

- فُتحت شاشة `Public Website Requests` وصفحة الطلب `id=14` بجلسة موظف فعلية على `backup-erp`، لكن لم تُنفذ أثناء هذا التصحيح أي عملية تغيير status أو ربط Third Party أو promotion حتى لا نغيّر سجل أعمال حقيقيًا.
- لم تُختبر rates تجارية فعلية من هذا التصحيح؛ قبول زر الإدارة لا يغير عقد البحث أو التسعير الذي تحقق في M043 الأساسي.
- لا يزال limiter المشترك أو الطرفي مطلوبًا قبل أي إنتاج عام؛ staging لا يُعد قبول production.

## نشر staging — M043-R01 / M043-C01

- فرع Dolibarr مرفوع: `feature/m043-public-website-intake` حتى helper pin `37b8e26`; كود runtime المعتمد عند `334c46c4316c8e75c1535e75f772a44e24cb80a7`.
- فرع React مرفوع: `feature/m043-dolibarr-public-gateway`; إعداد بوابة staging أضيف في PR رقم 13.
- نُشر artifact المقيد `m043-334c46c.tar.gz` على `backup-erp` فقط، SHA-256 `8dd4e625190d2c33de98e9e902bc6f34589b4b8d731f4833462a9a8b2a30224e`، وعدد ملفات runtime المسموح بها 17.
- نتائج الأداة المقيدة: `M043_STAGING_PREFLIGHT=PASS`, `M043_STAGING_APPLY=PASS`, `M043_STAGING_VERIFY=PASS`, و`PRODUCTION_ACCESSED=NO`.
- أُخذت نسخة كود قبل التطبيق عند `/root/backup-erp-code-backups/m043-before-334c46c-20260805T225143Z.tar.gz` ونسخة قاعدة بيانات عند `/root/backup-erp-db-backups/m043-before-334c46c-20260805T225143Z.sql` مع checksums مثبتة في Mission `M043-C01`.
- قبول المتصفح الموثق: زر Website Requests ظاهر في shared admin navigation وفي Quick Actions، يفتح القائمة وصفحة `id=14`; عرض 390×844 يضعه داخل القائمة المتجاوبة بصورة سليمة.
- لم يُلمس `chinaplanet.org` أو أي production.

## التطابق

- Public React: `LOCAL_BROWSER_VERIFIED`.
- Dolibarr service/database contract: `LOCAL_RUNTIME_VERIFIED`.
- Dolibarr staff UI: `STAGING_AUTHENTICATED_BROWSER_VERIFIED`.
- Authenticated Customer Center: `UNCHANGED / NOT_APPLICABLE`.
- Mobile app: `NOT_APPLICABLE`.
- n8n: `POSTPONED / NOT_USED`.

## التراجع

إرجاع ملفات M043 في الفرعين وتعطيل الصلاحيات/المسارات الجديدة. الجدول الإضافي يبقى dormant لحفظ الأدلة؛ لا يُحذف دون موافقة مستقلة على البيانات. لا يوجد rollback محاسبي أو WordPress order لأن M043 لم ينشئ أيًا منهما.
