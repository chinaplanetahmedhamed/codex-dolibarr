# M121 — خطة تنفيذ تعليمات China Planet OS
3 نوفمبر 2026. Work مسؤول القرارات والتنسيق؛ Codex مسؤول الهندسة؛ Dot للماك والمتابعة.
تفويض backup-erp والبيانات والحسابات التجريبية مسجل؛ لا انتظار موافقات جزئية ضمنه. الإنتاج خارج النطاق.
الترتيب التالي يعيد استخدام مفاتيح المهام الـ29 الموجودة في Drive، ولا يختلق أرقام Missions جديدة للميزات.

## قيود مشتركة
جميع وحدات ERP والتكاملات داخل custom/<module>/ في المستودع الرئيسي؛ SQL في sql/ والخدمات في class/ أو lib/. لا مشاريع ميزات منفصلة خارج الجذر. Docs upstream مصدر قائم مثبت الإصدار؛ دمجه ليس استنساخ منتج جديد.
لا تغيير مفاتيح DB أو حذف تاريخ أو ledger مكرر. لا استبدال بيانات حقيقية ببيانات تجريبية. مصادر الدفع والحالات موحدة، والصلاحيات server-side. backup-erp فقط.

| المهام القائمة | التنفيذ والملفات المالكة | القبول/الاختبار قبل التقدم |
| --- | --- | --- |
| P1-01 | تقرير المصدر وملكية الوحدات؛ docs/architecture/ وresources.md | مصدر/commit واضحان، فصل facts/reported/unknown |
| P1-02 | مطابقة runtime/schema/roles/version | نسخة احتياطية قابلة للتحقق؛ بيانات metadata فقط؛ مصادقة فعلية |
| P1-03 | جمع مصدر Boards/M116/Office في custom/ عبر manifest مدروس | لا ضياع dirty work؛ dependency closure؛ مقارنة الملف/المخطط |
| P1-04 | عزل staging وDocs ERP target | كل endpoint اختبار؛ منع outbound real emails/production writes |
| P1-05 | تعريف cash/credit/commitments/funding/source identity | أمثلة 50,000/18,000 =>32,000، و50,000/10,000=>40,000 مع التزام30,000 |
| P1-06 | قبول المرحلة الأولى وتثبيت baseline | لا قفزة للمالية مع source/runtime drift غير مفهوم |
| P2-01 | financial.lib.php وقراءات موحدة دون ledger جديد | native/CPCC/M116 source identity؛ لا double counting؛ لكل عملة |
| P2-02 | balance.php وtasks/account.php القائمة | كل المشاريع/مشروع/مهمة، إثبات ومورد، تاريخ وفلاتر واضحة |
| P2-03 | الموردون عبر native identities وخدمة قائمة/امتداد صغير داخل Operations | مورد واحد عبر عميلين ومشاريع متعددة؛ لا مطابقة اسم تلقائية |
| P2-04 | أمر دفع واحد وخيارات تمويل وتقسيم | insert once/retry idempotent/transaction rollback/owner validation |
| P2-05 | Open/Closed/History/financial settlement | Done لا يسدد؛ إعادة فتح تبقي الأثر التاريخي والعكس مسجلًا |
| P2-06 | Office expenses الحالي | تمويل شركة مستقل؛ لا تحميل عميل دون علاقة صريحة |
| P2-07 | اختبارات المالية | عملات، FX، rounding، partial payment، reversal، race، وصول/تصدير |
| P3-01 | boards.js/css والـrenderer القائم | Compact/Comfortable؛ عنوان وعميل بارزان؛ بحث عنوان ومرجع |
| P3-02 | أولوية كل بطاقة | شريط كامل ونص؛ عاجل أحمر/مرتفع برتقالي/عادي هادئ بكل كثافة |
| P3-03 | مقارنة مصورة مع Dolibarr | 1440/1280 و390px، zoom متطابق، AR/EN وRTL/LTR؛ touch/pen كـregression |
| P4-01 | Logistics commands/snapshots وروابط دقيقة | تغيير حالة يحفظ بالمصدر؛ container/loading/ETA/payments؛ عودة للسياق |
| P4-02 | Operations/checklists/inspection | الإنتاج/الفحص/التحميل وقيود الانتقال من مصدر واحد |
| P4-03 | تثبيت Docs renderer/templates ومعاينة قوالب | CI/PL/BL/COO/booking، القوالب نفسها؛ target isolation |
| P4-04 | تعديل/حفظ/رفع الوثيقة داخل سياق ERP | project/shipment/owner/version/visibility؛ لا ملف مكرر عند retry |
| P4-05 | CargoX/DHL | submission evidence/received/dispatch/deadline؛ لا تقديم خارجي وهمي |
| P5-01 | Today dashboard من مصادر مالكة | مفتوح/عاجل/متأخر/مستحقات/شحن/مستندات/blocked، permission-filtered |
| P5-02 | تنبيهات المواعيد وعدم التقدم | حدود التاريخ/timezone؛ dedup؛ لا إشعار للمسوّى/المكتمل |
| P6-01 | سيناريو staging مجهّل | عميل/مشروع/3موردين/حاوية/دفعات/إيداع/فحص/شحنة/وثائق/CargoX/DHL |
| P6-02 | مورد مشترك/تمويل شركة/history/reopen | الأرصدة من نفس الواقعة؛ لا افتراض تمويل العميل |
| P6-03 | صلاحيات وتكرار وفشل وتراجع | Admin/Staff/Customer، entity/customer isolation، forged IDs، CSRF |
| P6-04 | دليل وأدلة كل خطوة | expected/actual/version/role/fixture/screenshot/defect/retest قبل الخطوة التالية |
| REL-01 | artifact مثبت وbackup/rollback للاختبار | بصمة وmanifest، dependency/schema boundary، restore validation |
| REL-02 | تسليم النتيجة | staging status مفصل؛ الإنتاج ممنوع ما لم يتغير التفويض صراحة لاحقًا |

## تسلسل الاختبار على backup-erp
1. إثبات hostname وtargets، تعطيل/اعتراض إرسال البريد والإشعارات الحقيقية في نطاق اختبار معتمد، والتحقق من عزل البيانات. لا تفترض أن موقع docstest معزول.
2. نسخة كود وقاعدة بيانات وإعدادات لازمة دون طباعة أسرار؛ بصمات ومكان محمي وخطة استرجاع.
3. تطبيق artifact والمهاجرات الآمنة التي اجتازت اختبارات المصدر، ثم smoke مصادق وصلاحيات سلبية.
4. بيانات DEMO-M121 ذات أسماء مصطنعة؛ حسابات الاختبار بأقل صلاحيات وبدون تسريب اعتماديات أو نشرها بالتقرير.
5. إيداع50,000؛ سداد5,000+10,000+3,000؛ تحقق32,000. التزام منفصل لا يغير النقد. اختبر دفعة ممولة من الشركة وتقسيم مشاريع وإعادة إرسال.
6. تحقق supplier unified statement وproject/task/Financial Center projections والتاريخ والتسوية/إعادة الفتح.
7. نتائج وأدلة ودليل تشغيل بعد كل وظيفة. لا تُعد source lint أو HTTP200 نجاحًا end-to-end.

## عوائق تنفيذ حقيقية
الموافقة متاحة؛ الوصول المصادق والـbackup/SSH/deployment runtime غير متاحين في الجلسة. PHP/Docker غير متاحين محليًا. لا يُدّعى إجراء نسخة أو نشر أو اجتياز tests لم تعمل.
تنسيق الماك عبر التكليف المشترك MAC-GOV-20261103، مع تأكيد receipt منفصل؛ لا قناة مباشرة مؤكدة إلى Dot/جلسة Codex أخرى.
