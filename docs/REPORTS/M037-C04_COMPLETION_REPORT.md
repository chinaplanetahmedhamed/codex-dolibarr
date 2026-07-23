# تقرير إنجاز M037-C04

## النتيجة

`PASS / DEVELOPER_REPORTED`

أزيل اعتماد تصنيف حالات طلبات الشحن على دوال JSON في MariaDB. أصبحت الخدمة تفك بيانات الخدمة وتحدد الحالة في PHP، لذلك لا يؤدي JSON قديم غير صالح إلى فشل الاستعلام أو ظهور جميع العدادات بقيمة صفر.

## السياق

- Mission: `M037-C04`
- Branch: `integration/current-source`
- Base commit: `3167390`.
- Correction commit: `806df74a0f90ecf8d10c4627707c36d313001a82`.
- Release-pin commit: commit containing the corrected release pins on `integration/current-source`.
- البيئة: محلية فقط.

## الملفات

- `custom/chinaplanetoperations/class/OperationsService.class.php`
- `deploy/tests/test_m036_shipping_orders_source.php`
- `deploy/tests/test_m037_backup_erp_full_release.php`
- `deploy/tools/chinaplanetos_backup_erp_m037_full_release.sh`
- `deploy/tools/chinaplanetos_production_m037_full_release.sh`
- `deploy/tools/install_backup_erp_m037_full_release_helper.sh`
- `deploy/tools/install_production_m037_full_release_helper.sh`
- `docs/MISSIONS/M037-C04_MARIADB_SAFE_SHIPPING_CLASSIFICATION.md`
- `docs/REPORTS/M037-C04_COMPLETION_REPORT.md`
- `docs/CHANGELOG.md`
- `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md`

## قاعدة البيانات والإعدادات

- لا يوجد تغيير schema أو configuration أو module state.
- الاختبار التشغيلي أنشأ fixtures مؤقتة داخل transaction محلية، ثم نفذ rollback وتأكد من عدم بقاء بيانات.

## الصلاحيات وظهور بيانات العميل

- بقيت شروط `entity` و`fk_soc` وملكية الحجز والشحنة كما هي.
- لا تغيير في بوابة العميل أو Mobile API.
- لا تعرض الخدمة تكلفة مورد أو هامشًا أو بيانات موظف أو ملاحظات خاصة.

## الاختبارات

- PHP lint للخدمة واختبار المصدر: `PASS`.
- عقد المصدر: لا يوجد `JSON_EXTRACT` أو `JSON_VALID` في تصنيف lifecycle، ويوجد فك وتصنيف PHP: `PASS`.
- اختبار قاعدة MariaDB المحلية الحقيقي:
  `M036_RUNTIME=PASS total_delta=7 active=4 confirmed=1 closed=1 cancelled=1 legacy_json=SAFE ownership=ISOLATED foreign_entity=EXCLUDED rollback=CLEAN`.
- فحص المسافات والتنسيق عبر `git diff --check`: `PASS`.
- فحص إصدار M037 النهائي:
  `M037_RELEASE_TEST=PASS RUNTIME_FILES=52 SUPPORT_FILES=17 COMMANDS=6 PRODUCTION=FORBIDDEN`.
- artifact المصحح:
  `inputs/m037-full-806df74.tar.gz`
  بالـ SHA-256:
  `c0e5741f45018302983d6449a2e2392571162fbcd636e1484148f4dc932906e5`.
- helper الخاص بـ `backup-erp` مثبت محليًا على SHA-256:
  `83119faf9ea6b72ca5420ee859ae5b8d66f59b7d2674050146e1ae840cc4e85f`.
- helper الخاص بالإنتاج مثبت محليًا على SHA-256:
  `13f7e2462d68449a53f593f841f8afd4141432450907e984ab7475fa0161d65f`.
- تم بناء وفحص الحزمة والأدوات محليًا فقط؛ لم يتم رفعها أو تثبيتها أو تنفيذها على أي خادم.

## توافق الأسطح

- Admin ERP: `BACKEND_VERIFIED_LOCAL`.
- Customer Portal: `NOT_APPLICABLE`؛ لا تغيير في البيانات أو العرض.
- Mobile App: `NOT_APPLICABLE`؛ لا تغيير في API أو التصميم.
- Phone view: `NOT_APPLICABLE`؛ التغيير غير مرئي، وعقد التصميم المتجاوب الحالي لم يتغير.

## المخاطر والمتابعة

- إحصاء الحالات المصنفة يقرأ صفوف طلبات الشحن الحالية ويجمعها في PHP. هذا أدق مع البيانات القديمة، لكنه قد يحتاج أعمدة lifecycle مطبّعة في مهمة مستقبلية إذا أصبح الحجم كبيرًا.
- البحث المفلتر يطبق حد النتائج بعد التصنيف حتى لا يخفي SQL صفوف الحالة المطلوبة؛ البحث غير المفلتر يبقى محدودًا في SQL.
- لا يجوز إعادة استخدام artifact السابق لأنه يحتوي التنفيذ الذي فشل على ERP الحي.
- artifact المصحح أصبح checksum-pinned محليًا، لكن يلزم تصريح منفصل ودقيق قبل رفعه أو تثبيته أو تنفيذه على `backup-erp` أو ERP الحي.

## التراجع

استعادة النسخة السابقة من `OperationsService.class.php` واختبار المصدر تعيد التنفيذ السابق. لا توجد migration أو بيانات دائمة أو إعدادات تحتاج تراجعًا.
