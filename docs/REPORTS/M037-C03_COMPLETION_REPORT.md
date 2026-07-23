# تقرير إنجاز M037-C03

## النتيجة

`PASS / DEVELOPER_REPORTED`

عولج فشل اختبار تشغيل سجل طلبات الشحن مع بيانات قديمة تحتوي JSON غير صالح. أصبحت قراءة حالة الشحن تستخدم كائن JSON فارغًا كبديل آمن قبل `JSON_EXTRACT`، من دون تغيير معنى الحالات أو الصلاحيات أو نطاق الكيان والعميل.

## الملفات

- `custom/chinaplanetoperations/class/OperationsService.class.php`
- `deploy/tests/test_m036_shipping_orders_runtime.php`
- `deploy/tests/test_m036_shipping_orders_source.php`
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

## توافق الأسطح

- Admin ERP: تصحيح backend محلي مثبت.
- Customer Portal: `NOT_APPLICABLE`؛ لا تغيير في العقد أو العرض.
- Mobile App: `NOT_APPLICABLE`؛ لا تغيير في API أو التصميم.
- Phone view: `NOT_APPLICABLE`؛ لا يوجد تغيير مرئي.

## حدود البيئة

تم التنفيذ والاختبار محليًا فقط. لم يتم الوصول إلى `backup-erp` أو ERP الحي.

## المخاطر والمتابعة

- القيم القديمة غير الصالحة تُعامل كحالة افتراضية آمنة بدل إسقاط الاستعلام كله.
- يلزم artifact جديد مقفول بالـ checksum وأداة نشر مقيّدة.
- لا يجوز رفع أو تطبيق الحزمة الجديدة دون موافقة نشر صريحة منفصلة.

## التراجع

استعادة النسخة السابقة من `OperationsService.class.php` تعيد سلوك القراءة السابق. لا توجد migration أو بيانات دائمة تحتاج تراجعًا.
