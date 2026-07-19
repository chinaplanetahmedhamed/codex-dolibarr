# ChinaPlanet ERP Customer App / تطبيق كوكب الصين

تطبيق العملاء الرسمي الخاص بـ China Planet، مبني بـ Expo وReact Native وTypeScript ليعمل على Android وiPhone والويب للمعاينة.

## حدود المشروع

- التطبيق عميل جوال داخل مستودع ChinaPlanetOS ولا يعدّل ملفات Dolibarr أو وحدات ERP.
- يظل Dolibarr هو النظام الخلفي ومصدر البيانات الرئيسي.
- يتصل التطبيق بطبقة API المجمدة في M017، ولا يتصل مباشرة بقاعدة بيانات Dolibarr.
- لا يُسمح للتطبيق بالوصول المباشر إلى قاعدة بيانات Dolibarr.
- يجب حصر كل عميل في مشاريعه وشحناته وطلباته ومستنداته وبياناته المالية المسموح بها فقط.
- لا تُعرض بيانات الموردين أو التكلفة أو هامش الربح أو الملاحظات والملفات الداخلية.

## M017 — التكامل المحلي الآمن

- تسجيل دخول العميل الخارجي عبر `/api/mobile/v1` دون إرسال `socid` أو `entity` من التطبيق.
- access token قصير العمر وrefresh token دوّار محفوظان في SecureStore فقط.
- الصفحة الرئيسية، المشاريع، الشحنات، الحساب متعدد العملات، الطلبات، فواتير الخدمات، المستندات التجارية والتقارير.
- تنزيل الملفات من endpoints محمية، مع إزالة cache العميل عند تسجيل الخروج.
- العربية افتراضيًا والإنجليزية متاحة، مع RTL/LTR وحالات loading/empty/offline/error.
- لا يوجد fallback إلى fixtures في تشغيل العميل.

الإشعارات خارج M017. إعداد فروع GitHub والبناء الداخلي والنشر المحمي للمتاجر موثق في `docs/RELEASE_PROCESS.md`; لا يعمل نشر المتاجر حتى تُجهز الحسابات المملوكة للشركة وتُعتمد بيئة `mobile-production` يدويًا.

## التشغيل المحلي

```bash
npm install
npm run typecheck
npm run lint
npm test
npm run web
```

يمكن لاحقًا تشغيل `npm run ios` أو `npm run android` بعد تجهيز محاكي المنصة، أو استخدام Expo Go وفق إصدار Expo المعتمد.

## إعداد API المحلي

القيمة الافتراضية محلية فقط:

`http://127.0.0.1:8088/api/index.php/chinaplanetcustomercenter/mobile/v1`

ملفات البناء تستخدم `EXPO_PUBLIC_APP_ENV` بقيمة `local` أو `backup-erp` أو `production`. يجب تمرير `EXPO_PUBLIC_API_BASE_URL` صراحة لأي بيئة غير محلية، ولا توجد عناوين staging أو production مضمنة في المصدر. بناء production يتطلب كذلك بوابة `EXPO_PUBLIC_PRODUCTION_APPROVED=true` بعد الموافقة الدقيقة المنفصلة. يمكن للمطور محليًا تغيير العنوان للمحاكي عند الحاجة.

ملفا EAS `internal` و`production` هما الاستثناء المراجع لهذه القاعدة: كلاهما يثبت عنوان Mobile API العام على ERP الحي عبر HTTPS، من دون أي مفتاح أو بيانات دخول. فرع `develop` مخصص للاختبار الداخلي، و`main` وحده مصدر المتاجر.

لا تضع كلمات مرور أو tokens أو مفاتيح ERP في متغيرات Expo؛ القيم `EXPO_PUBLIC_*` مضمنة في التطبيق وليست أسرارًا.

المصدر الرسمي موجود داخل مستودع ChinaPlanetOS في `mobile/chinaplanet-customer-app/`. لا تُحفظ APK/AAB/IPA أو `node_modules` أو credentials أو بيانات عميل في Git.
