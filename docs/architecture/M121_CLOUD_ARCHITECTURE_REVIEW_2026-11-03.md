# M121 — تقرير تدقيق المصدر والمعمارية
تاريخ: 3 نوفمبر 2026. المرجع: تعليمات أحمد حامد الكاملة لتحسين وتكامل China Planet OS.
الحالة: تدقيق مصدر قابل للمراجعة؛ Phase 1 ما زالت open لأن مطابقة التشغيل والمخطط والصلاحيات وعزل بيئة الاختبار غير مكتملة. لا تغيير برمجي أو بيانات أو نشر في هذا التشغيل.

## التفويض وحدود التشغيل
أحمد فوّض Work اتخاذ قرارات التنفيذ والنسخ الاحتياطي والنشر والاختبار على backup-erp دون موافقات جزئية، بما يشمل بيانات تجريبية وحسابات اختبار Admin/Staff. الإنتاج ممنوع تمامًا.
تبقى حماية البيانات والنسخ الاحتياطي والتراجع والتحقق من الهدف والصلاحيات سارية. التفويض لا ينشئ جلسة دخول أو مفاتيح وصول تلقائيًا.

## دليل المصادر
| المصدر | الإصدار المقروء |
| --- | --- |
| ERP main | 36de6182b8828f13f55ad45f5be4843b0b3cd9b0؛ تغييرات الحوكمة فقط منذ baseline التطبيق 317ef5e2eb00729aaa9f3b62427bead7273c25a5 |
| Boards | codex/m107-customer-project-workspaces عند 113ddc6855915061d14acc7ab61ea33fd31742f9 |
| حساب العميل M116 | 0fb1dcc89d33760cca3a1b42c83979ba2fe89e77 |
| Office Expenses | codex/m119-office-expenses-local عند 53ac3ca9ed5eccbaf8c879379bcff19c6683b51b |
| Docs | shipping-doc-generator/main عند 04f3d240af5ad5e3764a81b7712d796cdbb0a221 |

قُرئت 65 ملف SQL من المصادر المحددة: 77 ظهورًا لتعريف جدول و76 اسم جدول مختلفًا؛ لا تمثل الجداول المثبتة في قاعدة الاختبار أو الإنتاج. الفهارس وأعمدة fk_* من المصدر ليست إثباتًا لتطبيق كل قيد في runtime.
تقارير M121 المحلية و29 مهمة مسجلة بالفعل في Drive. وصل تسليم الماك أثناء المراجعة، وقُرئت نسخ audit/plan/missions/resources وتقرير MAC-GOV؛ ما يلي يميّز المصدر المحلي المنقول عن GitHub المقروء مباشرة. تقرير SSH/137 DDL-source سابق معلومة منقولة من السجل المشترك، وليس اختبارًا أعيد هنا.

## الوحدات والمسارات
| الوحدة | المسار | إصدار descriptor في main | الملكية |
| --- | --- | --- | --- |
| Customer Center | custom/chinaplanetcustomercenter/ | 2.3.2 | بوابة وإسقاطات مالية والتزامات ووثائق؛ لا استبدال مالية Dolibarr |
| Operations | custom/chinaplanetoperations/ | 2.7.0 | عمليات ومهام وربط العميل/المشروع؛ تفاصيل Boards الأحدث في المصدر المحدد |
| Logistics | custom/chinaplanetlogistics/ | 1.3.0 | الشحنة وأحداثها ومتطلبات المستندات |
| Shipping Prices | custom/chinaplanetshippingprices/ | 1.5.0 | أسعار واستيراد ومراجعة ونشر وطلبات حجز |
| cpkanban | custom/cpkanban/ | 2.1.0 | Kanban قائم؛ تحديد حدوده قبل استبدال أو إلغاء |
| Vision UI | custom/visiontradingui/ | 0.1.0 | shell/hooks وعرض، لا ملكية مالية جديدة |
| Office Expenses | custom/chinaplanetofficeexpenses/ في فرع Office | غير مثبت هنا | مصروفات الشركة؛ لا افتراض أنها ممولة من عميل |
| Docs | shipping-doc-generator القائم | commit مثبت أعلاه | مولد React/Node وتكامل ERP؛ إعادة استخدام المنطق والقوالب |

إصدارات descriptors ليست إصدارات runtime المثبتة. يبقى Dolibarr مرجع العملاء والموردين والمشاريع والفواتير والمدفوعات والبنوك الأصلي.

## خريطة العلاقات
- cpo_operation: entity, fk_soc, fk_project, fk_commande, fk_shipment, fk_cpkanban_card.
- cpo_task.fk_operation؛ cpo_project_task يربط fk_task بالمشروع وfk_native_task مع uniqueness حسب entity.
- cpo_project_board يربط مشروعًا ببورده دون Project بديل.
- cpcc_project_charge: fk_soc, fk_project, fk_supplier؛ cpcc_financial_entry.fk_charge وsource_type/source_id.
- M116: cpo_account_entry + cpo_account_allocation(fk_entry,fk_project,fk_task)، وأوامر idempotency وتدقيق وعكس.
- chinaplanetlogistics: fk_soc/fk_project/fk_commande.
- cpl_document_requirement: fk_shipment/fk_soc مع visibility/action/deadline؛ cpcc_export_document: عميل/مشروع/شحنة.
هذه روابط مصدر؛ يجب التحقق من السجلات اليتيمة والتعارضات والفهارس في قاعدة الاختبار دون نسخ بيانات مالية خاصة إلى التقارير.

## فحص Financial Portfolio المطلوب
المسار: custom/chinaplanetcustomercenter/admin/financial_portfolio.php.
المصدر main، blob df7ccdc4793010fc747024ba1c4a947464c1371d.
الموجود: ملخص عميل وعملات وفواتير متأخرة وروابط كشف/إدخال وAs-of.
الفجوات:
1. financial.lib.php يجمع المقبوضات الأصلية ويخصم native_invoice_allocation/native_credit_allocation. لذلك available يمثل أثر استخدام ائتمان الفاتورة أيضًا، وليس تلقائيًا «النقد المقبوض ناقص المدفوع فعليًا للموردين».
2. الصفحة تجمع supplier_remaining وfuture_services عبر العملات في KPIs، وتجمع obligations/required_funding عبر العملات على صف العميل دون تحويل. جدول العملات موجود لكنه لا يصحح الأرقام المجمعة الأخرى.
3. عمود fk_supplier موجود في جدول الالتزام؛ addcharge المفحوص يكتب supplier_label فقط، والقراءة تعرض الاسم. يجب إعادة استخدام هوية المورد لا ربط الأسماء تخمينًا.
4. commitment_rows يقيّد customer_visible=1 ويستخدمه ملخص داخلي؛ يلزم فصل رؤية العميل عن شمول دين المورد للموظف المصرح.
5. فلترة المشروع في financial_summary لا تُمرر إلى استعلام unpaid_invoices؛ يلزم اختبار هل الملخص يقصد كل العميل أم المشروع وعرض ذلك صراحة.
لا تثبت هذه المشاهدات مقدار خطأ فعلي لحساب عميل بعينه؛ لم تُقرأ أو تُعدّل سجلات مالية حقيقية.

## فحص Boards المطلوب
custom/chinaplanetoperations/tasks/boards.php غير موجود في main المفحوص؛ موجود في مصدر Boards أعلاه.
يعتمد على InternalTaskBoardsService وboards.js وboards.css ويفتح البطاقة بسياق iframe. توجد workspace/gallery/myWork/search/shared/mutate، مشاركة وأرشيف وإتمام/إعادة فتح.
المصدر يحتوي أعمدة 290px وبطاقات padding 12px وأولويات ومواعيد. لم تُقَس الواجهة rendered؛ قبول Compact/Comfortable والأولوية كاملة العرض يحتاج مقارنة Dolibarr بنفس viewport/zoom وRTL/LTR والهاتف.
لا يجوز نشر main على أنه نسخة كاملة من التطبيق الجاري؛ توجد وظائف أحدث على فروع مختلفة وأعمال محلية غير مفحوصة.

## المالية والتكامل
native Dolibarr finance، CPCC projections، M116 cashbook، وOffice تمثيلات مختلفة. لا إنشاء ledger رابع.
قرارات التصميم للعمل:
- فصل cash custody عن invoice credit وعن commitments؛ النقد والالتزامات حسب العملة.
- إثبات source identity لكل دفع وربطه بالمستفيد والدافع والتمويل والمشروع/المهمة دون افتراض.
- إعادة استخدام idempotency/reversal/audit القائمة عندما يطابق عقدها الواقعة المالية، وعدم نسخ نفس الدفع إلى عدة جداول كمصادر مستقلة.
- Operational Done لا يعني Financial Settled. Open الافتراضي مع Closed/History/فترة وإعادة فتح.
تحتاج قواعد الانتقال ومطابقة السجل الحقيقي إلى تدقيق staging قبل اعتماد الأرقام.

## Docs وشحن ووثائق
المصدر القائم يحتوي dolibarr.searchShipments وsyncSelected مع staff/admin gate وفحص تطابق customer/target shipment قبل رفع وثائق الشحنة.
M002 في مستودع Docs يذكر docstest وERP production؛ هذا ليس دليل عزل الاختبار. لا fixture أو upload حتى يثبت أن كل endpoints المستعملة تستهدف backup-erp.
يُعاد استخدام renderer والقوالب كما هي وظيفيًا، مع adapter داخل custom في تصميم ERP؛ لا إعادة بناء مولد متنافس.
CargoX/DHL: سجلات مستندات/أدلة/حالات ومواعيد وروابط أولًا؛ لا ادعاء وجود تقديم خارجي أو tracking API دون فحص.

## الوصول الفعلي والاختبارات
- فُتحت https://backup-erp.visiontrading.biz/ فقط. الصفحة Login @ 24.0.1 وتعرض Dolibarr 24.0.1؛ هذه ملاحظة صفحة دخول، لا قبول وظيفي.
- لا جلسة إدارية مصادقة. لم تُدخل كلمات سر أو تُنشأ حسابات.
- بيئة التنفيذ لا تحتوي مفاتيح SSH أو GH_TOKEN/GITHUB_TOKEN أو Docker/PHP؛ اتصال Hostinger إداري غير مهيأ. GitHub connector يتيح المصدر والوثائق، ولا يعرض dispatch نشر.
- أداة ERP المتصلة تولّد مسارًا نسبيًا فقط، فلا تثبت origin/عزل الهدف. لم تُستخدم لإنشاء بيانات قبل تحقق الوجهة والنسخ الاحتياطي.
- لذلك لم تُنفذ backup أو deployment أو اختبارات مالية/UI مصادقة. فحوص المصدر والوثائق فقط تمت؛ runtime/functional tests: BLOCKED.

## بوابات التقدم
1. وصل تسليم M121 وحوكمة الماك في commit729efb24195108f1b7afc58422c9f1da5f01783d. استكمال مطابقة المصدر/dirty paths فعليًا على الماك قبل الكتابة؛ لا reset/clean.
2. وصول اختبار فعلي يتيح نسخة احتياطية واسترجاعًا موثقًا ومطابقة إصدار/schema/grants والهدف.
3. manifest يجمع الوحدات داخل custom من مصادرها الصحيحة مع حفظ كل وظيفة وبيان اختلافاتها.
4. تنفيذ الخطة على نسخة الاختبار ثم fixtures مجهلة واختبارات سلبية ومراجعة النتائج.
الإنتاج ممنوع في التفويض الحالي، حتى لو اجتاز backup-erp الاختبارات.

## تسليم الماك الذي وصل أثناء العمل
السجل المشترك أبلغ عن ترحيل30 وثيقة وحفظ حالة26checkout وأعاد أدلة M121. قُرئت نسخ التقرير والخطة والسجلات. الإصدار المحلي Customer Center2.5.0 وOperations2.9.0 وLogistics1.4.0 يختلف عن remote main؛ توجد CustomerFundsService وOperationSupplierWorkflowService وSharedDocumentService محلية أحدث. لذلك نتائج main هي فحص ذلك الإصدار المحدد وليست دعوة لاستبدال هذه الخدمات. استُخدم السجل المحلي كأساس missions/resources بدل إلغائه.
المراجعة المحلية مصدر evidence مُسلّم، وليست تنفيذ أوامر Mac من جلسة Work. متابعة الاختبار والنشر مُكلّفة عبر ملف التسليم المشترك وتنتظر receipt جديدًا.
