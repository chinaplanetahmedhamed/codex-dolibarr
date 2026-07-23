# تقرير إنجاز M037-C03

## النتيجة

`PASS / DEVELOPER_REPORTED`

عولج فشل اختبار تشغيل سجل طلبات الشحن مع بيانات قديمة تحتوي JSON غير صالح. أصبحت قراءة حالة الشحن تستخدم كائن JSON فارغًا كبديل آمن قبل `JSON_EXTRACT`، من دون تغيير معنى الحالات أو الصلاحيات أو نطاق الكيان والعميل.

## الملفات

- `custom/chinaplanetoperations/class/OperationsService.class.php`
- `deploy/tests/test_m036_shipping_orders_runtime.php`
- `deploy/tests/test_m036_shipping_orders_source.php`
- `deploy/tests/test_m037_backup_erp_full_release.php`
- `deploy/tools/chinaplanetos_backup_erp_m037_full_release.sh`
- `deploy/tools/chinaplanetos_production_m037_full_release.sh`
- `deploy/tools/install_backup_erp_m037_full_release_helper.sh`
- `deploy/tools/install_production_m037_full_release_helper.sh`
- `docs/MISSIONS/M037-C03_PRODUCTION_DATA_SAFE_SHIPPING_RUNTIME.md`
- `docs/REPORTS/M037-C03_COMPLETION_REPORT.md`
- `docs/CHANGELOG.md`
- `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md`

## قاعدة البيانات والإعدادات

- لا يوجد تغيير schema أو إعدادات.
- أضاف الاختبار سجلات مؤقتة داخل transaction محلية ثم نفذ rollback وتحقق من عدم بقائها.

## الصلاحيات وظهور بيانات العميل

- لم تتغير قواعد `entity` أو `fk_soc`.
- لم تتغير بوابة العميل أو البيانات المرئية له.
- لم تُكشف تكاليف أو هوامش أو موردون أو ملاحظات خاصة.

## الاختبارات

- PHP lint للخدمة واختبار runtime: `PASS`.
- `deploy/tests/test_m036_shipping_orders_source.php`: `PASS`.
- الاختبار المحلي المتصل بقاعدة التشغيل:
  `M036_RUNTIME=PASS total_delta=7 active=4 confirmed=1 closed=1 cancelled=1 legacy_json=SAFE ownership=ISOLATED foreign_entity=EXCLUDED rollback=CLEAN`.
- `deploy/tests/test_m037_backup_erp_full_release.php`:
  `M037_RELEASE_TEST=PASS RUNTIME_FILES=52 SUPPORT_FILES=17 COMMANDS=6 PRODUCTION=FORBIDDEN`.
- فحص صياغة أدوات Bash الأربع: `M037_SHELL_SYNTAX=PASS`.
- فحص الحزمة: `PASS`؛ تحتوي بالضبط على 71 ملفًا عاديًا (`52 runtime + 17 support + 2 manifests`) وتطابق القوائم المسموح بها، دون روابط أو مسارات إضافية.

## توافق الأسطح

- Admin ERP: تصحيح backend محلي مثبت.
- Customer Portal: `NOT_APPLICABLE`؛ لا تغيير في العقد أو العرض.
- Mobile App: `NOT_APPLICABLE`؛ لا تغيير في API أو التصميم.
- Phone view: `NOT_APPLICABLE`؛ لا يوجد تغيير مرئي.

## حدود البيئة

تم التنفيذ والاختبار وبناء الحزمة محليًا فقط. لم يتم الوصول إلى `backup-erp` أو ERP الحي، ولم يتم رفع أو تطبيق أي ملف على خادم.

## حزمة التصحيح المقفلة

- Runtime commit: `04bf7780a0165afe8938f640bff7434214944905`.
- Artifact: `m037-full-04bf778.tar.gz`.
- Artifact SHA-256: `6a55707932d683e33cd2ad96c085be7fefa432ee17a34a4d7fed3d38366f4f99`.
- Backup helper SHA-256: `bb9bf731da6f2d7d109ccf608d6fa0b0314f5654e9f668df015b35f2750d624a`.
- Production helper SHA-256: `0bdec24f6e48feb86adbf4d12fe570209ce62fd02445a085b9d69be1e7ed6539`.
- Backup installer SHA-256: `47df625b50f6fc8deffd49001cbb73fdff723e7532249ceb64904b8980e452e3`.
- Production installer SHA-256: `ab28e1a55c7a7199ab84e548d45e6a53827ff55c474d67ef31bd6d1365e3da76`.

## المخاطر والمتابعة

- القيم القديمة غير الصالحة تُعامل كحالة افتراضية آمنة بدل إسقاط الاستعلام كله.
- بُني artifact جديد مقفول بالـ checksum وأداتان مقيّدتان للمرحلتين، لكنهما لم تُثبتا ولم تُشغلا.
- لا يجوز رفع أو تطبيق الحزمة الجديدة دون موافقة نشر صريحة منفصلة.

## التراجع

استعادة النسخة السابقة من `OperationsService.class.php` تعيد سلوك القراءة السابق. لا توجد migration أو بيانات دائمة تحتاج تراجعًا.
